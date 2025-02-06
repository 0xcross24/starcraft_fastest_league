<?php

namespace App\Http\Controllers;

use App\Models\Replay;
use App\Models\User;
use App\Models\Stats;
use App\Models\Season;
use App\Services\EloService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ReplayController extends Controller
{
    protected $eloService;
    protected $statsController;

    public function __construct(EloService $eloService, StatsController $statsController)
    {
        $this->eloService = $eloService;
        $this->statsController = $statsController;
    }

    public function index()
    {
        return view('replays.index');
    }

    public function upload(Request $request)
    {
        // Validate the uploaded file
        $request->validate([
            'file' => 'required|file|max:2048', // Accept any file type, limited to 2MB
        ]);

        // Check if the file is valid
        if ($request->file('file')->isValid()) {
            if ($request->file('file')->getClientOriginalExtension() !== 'rep') {
                return back()->with('error', 'Only .rep files are allowed.');
            }

            // Store the file in the 'uploads' directory on the 'public' disk
            $fileName = $request->file('file')->store('uploads', 'public');
            $filePath = "/var/www/html/public/storage/$fileName";

            // Return success response
            $scriptPath = '/var/www/html/screp'; // Update with the actual path to your script
            exec("$scriptPath $filePath", $output, $return_var);

            // Debug the file path with dd
            if ($return_var === 0) {
                $jsonOutput = implode("\n", $output);
                $data = json_decode($jsonOutput, true); // Decodes JSON into an associative array


                // Check if the JSON decoding was successful
                if (json_last_error() === JSON_ERROR_NONE) {

                    $teams = [
                        'Team1' => [],
                        'Team2' => [],
                    ];

                    // Extract specific data from the JSON output
                    $players = $data['Header']['Players'];
                    $startTime = $data['Header']['StartTime'];
                    $winnerTeam = $data['Computed']['WinnerTeam'];
                    $playerDescs = $data['Computed']['PlayerDescs'];

                    $playerAPMs = [];
                    $playerEAPMs = [];
                    foreach ($playerDescs as $desc) {
                        $playerAPMs[$desc['PlayerID']] = $desc['APM'];
                        $playerEAPMs[$desc['PlayerID']] = $desc['EAPM'];
                    }

                    foreach ($players as $player) {
                        // Check if Team exists in the player array
                        $teamNumber = $player['Team'] ?? null;
                        $apm = $playerAPMs[$player['ID']] ?? null;
                        $eapm = $playerEAPMs[$player['ID']] ?? null;

                        // Only proceed if Team exists
                        if ($teamNumber) {
                            $teamKey = 'Team' . $teamNumber; // e.g., "Team1", "Team2"

                            // Add player details to the corresponding team
                            $teams[$teamKey][] = [
                                'ID' => $player['ID'],
                                'Name' => $player['Name'],
                                'StartTime' => $startTime,
                                'IsWinner' => ($teamNumber == $winnerTeam),
                                'Race' => $player['Race']['Name'],
                                'Team' => $teamNumber,
                                'APM' => $apm,
                                'EAPM' => $eapm
                            ];
                        }
                    }

                    return $this->store($teams, $filePath);
                } else {
                    // Return an error if JSON decoding fails
                    return back()->with('error', 'File failed to decode.');
                }
                return back()->with('success', 'File uploaded successfully. Script executed.')->with('file', $filePath);
            } else {
                return back()->with('error', 'File uploaded successfully, but script execution failed.');
            }
        } else {
            return back()->with('error', 'The uploaded file is not valid.');
        }
    }

    public function store($data, $filePath)
    {
        // Hash the filePath to ensure there's no duplicate replay files being uploaded
        $hashedFile = hash_file('sha256', $filePath);
        $uuid = Str::uuid()->toString();

        // Check which season is currently active
        $currentSeason = Season::where('is_active', 1)->first();

        if (!$currentSeason) {
            return redirect()->back()->with('error', 'No active season found. Please activate a season.');
        }

        // Check if a replay with this hash already exists
        if (Replay::where('hash', $hashedFile)->exists()) {
            return back()->with('error', 'Replay already exists');
        }

        $playerNames = []; // Array to store player names for validation
        $playersData = []; // Array to store validated player data

        foreach ($data as $id) {
            foreach ($id as $player) {
                // Validate player information
                $playerName = $player['Name'] ?? null;
                if ($playerName) {
                    $playerNames[] = $playerName; // Collect player names
                    $playersData[] = [
                        'player' => $player,
                        'name' => $playerName,
                    ];
                }
            }
        }

        $lowercasePlayerNames = array_map('strtolower', $playerNames);
        // Check if all users are registered
        $users = User::whereIn(DB::raw('LOWER(player_name)'), $lowercasePlayerNames)->get();
        $registeredNames = $users->pluck('player_name')->map('strtolower')->toArray();

        // Check for unregistered players
        $unregisteredPlayers = array_diff($lowercasePlayerNames, $registeredNames);
        if (!empty($unregisteredPlayers)) {
            return redirect()->back()->with('error', 'Failed to upload. Missing player(s): ' . implode(', ', $unregisteredPlayers));
        }

        $winners = [];
        $losers = [];

        foreach ($playersData as $playerData) {
            $isWinner = $playerData['player']['IsWinner'] ?? false;
            $playerName = $playerData['name'];

            $winningTeam = $isWinner ? 1 : 0;

            $user = $users->where('player_name', $playerName)->first();

            if ($isWinner) {
                $winners[] = $user->id;
            } else {
                $losers[] = $user->id;
            }
        }

        $eloResults = $this->statsController->calculateElo($winners, $losers);

        // Now we can safely create the replay records
        foreach ($playersData as $playerData) {
            $playerName = $playerData['name'];
            $player = $playerData['player'];
            $startTime = $player['StartTime'] ?? null;
            $startTimeFormatted = date('Y-m-d H:i:s', strtotime($startTime));
            $isWinner = $player['IsWinner'] ?? false; // Assuming IsWinner is boolean

            $user = $users->where('player_name', $playerName)->first();

            // Determine winning team based on player’s IsWinner status
            $winningTeam = $isWinner ? 1 : 0;

            $points = isset($eloResults['eloChanges'][$user->id]) ? $eloResults['eloChanges'][$user->id] : 0;

            // Create the replay entry
            Replay::create([
                'replay_id' => $uuid,
                'player_name' => $playerName,
                'winning_team' => $winningTeam, // 1 or 0
                'start_time' => $startTimeFormatted,
                'team' => $player['Team'], // Assuming team is based on player ID or adjust as needed
                'race' => $player['Race'],
                'apm' => $player['APM'],
                'eapm' => $player['EAPM'],
                'hash' => $hashedFile, // Store the hash
                'user_id' => $user->id,
                'file_name' => $filePath,
                'points' => $points,
                'season_id' => $currentSeason->id,
            ]);
        }

        return redirect()->route('replays.results', ['uuid' => $uuid]);
    }

    public function display($uuid)
    {
        $replays = Replay::where('replay_id', $uuid)->get();
        return view('replays.results', compact('replays', 'uuid'));
    }

    public function displayPlayer($user)
    {
        $user = User::where('player_name', $user)->first();

        //if (!$user) {
        //return redirect()->route('home')->with('error', 'User not found');
        //}

        // Get all seasons
        $seasons = Season::all();

        // Get all replay IDs where the authenticated user is a player
        $replay_ids = Replay::where('player_name', $user->player_name)->pluck('replay_id');

        // Get replays for the authenticated user and their opponents using the IN clause
        $replays = Replay::whereIn('replay_id', $replay_ids)->get();

        $user_ids = $replays->pluck('user_id')->unique();
        $statsCollection = Stats::whereIn('user_id', $user_ids)->get();

        // Map user stats to their corresponding user IDs for easier access
        $userStats = $statsCollection->keyBy('user_id');

        // Get stats from logged in user
        $stats = Stats::where('user_id', $user->id)->first();
        $rank = $this->eloService->getEloGrade($user->stats->elo);

        // Return the dashboard view with the replays data
        return view('player', compact('user', 'replays', 'userStats', 'stats', 'rank', 'seasons'));
    }
}
