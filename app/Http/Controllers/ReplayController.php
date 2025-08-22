<?php

namespace App\Http\Controllers;

use App\Models\Replay;
use App\Models\User;
use App\Models\Stats;
use App\Models\Season;
use App\Services\EloService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Log;

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
                return back()->with('error', 'An unexpected error has occurred.');
            }

            // Store the file in the 'uploads' directory on the 'public' disk
            $fileName = time() . '.rep';  // Ensure the file is saved with a .rep extension
            $fileRep = $request->file('file')->storeAs('uploads', $fileName, 'public');
            $filePath = "/var/www/html/storage/app/public/$fileRep";

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

                    // Check if the startTime is older than 48 hours
                    $currentTime = new DateTime('now', new DateTimeZone('UTC')); // Get current UTC time
                    $startTimeObj = new DateTime($startTime, new DateTimeZone('UTC')); // Convert startTime to DateTime object in UTC

                    $interval = $currentTime->diff($startTimeObj); // Calculate the difference
                    $hoursDifference = ($interval->d * 24) + $interval->h; // Convert difference to hours

                    /*if ($hoursDifference > 48) {
                        // Delete the
                        unlink($filePath);
                        // Reject upload if the replay file's startTime is more than 48 hours old
                        return back()->with('error', 'Replay file is too old. Please upload within 48 hours');
                    }*/

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
        // ...existing code...
        $playerNames = []; // Array to store player names for validation
        $playersData = []; // Array to store validated player data

        // Debug: log player names from replay and registered user names (after they are set)
        // We'll move this after $playerNames and $registeredNames are set below

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

        // ...existing code...
        $playerNames = []; // Array to store player names for validation
        $playersData = []; // Array to store validated player data

        // ...existing code...

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

        // After $playerNames and $registeredNames are set
        $lowercasePlayerNames = array_map('strtolower', $playerNames);
        $users = User::whereIn(DB::raw('LOWER(player_name)'), $lowercasePlayerNames)->get();
        $registeredNames = $users->pluck('player_name')->map('strtolower')->toArray();

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

            // Case-insensitive user lookup
            $user = $users->first(function ($u) use ($playerName) {
                return strtolower($u->player_name) === strtolower($playerName);
            });
            if (!$user) {
                // Optionally log or handle the missing user
                continue;
            }
            if ($isWinner) {
                $winners[] = $user->id;
            } else {
                $losers[] = $user->id;
            }
        }

        // Determine format (2s or 3s) based on number of players per team
        $team1Count = isset($data['Team1']) ? count($data['Team1']) : 0;
        $team2Count = isset($data['Team2']) ? count($data['Team2']) : 0;
        $format = null;
        if ($team1Count === 2 && $team2Count === 2) {
            $format = '2v2';
        } elseif ($team1Count === 3 && $team2Count === 3) {
            $format = '3v3';
        }

        $eloResults = $this->statsController->calculateElo($winners, $losers, $format);

        // Now we can safely create the replay records
        foreach ($playersData as $playerData) {
            $playerName = $playerData['name'];
            $player = $playerData['player'];
            $startTime = $player['StartTime'] ?? null;
            $startTimeFormatted = date('Y-m-d H:i:s', strtotime($startTime));
            $isWinner = $player['IsWinner'] ?? false; // Assuming IsWinner is boolean

            // Case-insensitive user lookup
            $user = $users->first(function ($u) use ($playerName) {
                return strtolower($u->player_name) === strtolower($playerName);
            });
            if (!$user) {
                // Optionally log or handle the missing user
                continue;
            }
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
                'replay_file' => $filePath,
                'points' => $points,
                'season_id' => $currentSeason->id,
                'format' => $format,
            ]);
        }

        // Preserve format in redirect if available
        $format = request('format', '2v2');
        return redirect()->route('player', ['user' => Auth::user()->player_name, 'format' => $format]);
    }

    public function displayPlayer($user)
    {
        $user = User::where('player_name', $user)->first();

        // Get all seasons
        $seasons = Season::all();
        // Use selected season from query or fallback to latest season
        $seasonId = request('season');
        if (!$seasonId && $seasons->count()) {
            $seasonId = $seasons->max('id');
        }
        $format = request('format', '2v2');

        // Get all replay_ids for this user, season, and format
        $replayIds = Replay::where('player_name', $user->player_name)
            ->where('season_id', $seasonId)
            ->where('format', $format)
            ->pluck('replay_id');

        // Get all replay rows for those replay_ids, season, and format
        $replays = Replay::whereIn('replay_id', $replayIds)
            ->where('season_id', $seasonId)
            ->where('format', $format)
            ->get();

        // For stats and rank, still use selected format
        $user_ids = $replays->pluck('user_id')->unique();
        $statsCollection = Stats::whereIn('user_id', $user_ids)->where('format', $format)->where('season_id', $seasonId)->get();
        $userStats = $statsCollection->keyBy('user_id');

        // Get stats for the selected format and season
        $stats = Stats::where('user_id', $user->id)
            ->where('season_id', $seasonId)
            ->where('format', $format)
            ->first();
        $rank = $stats ? $this->eloService->getEloGrade($stats->elo) : null;

        // Calculate numeric rank for the user in this season/format
        $allStats = Stats::where('season_id', $seasonId)
            ->where('format', $format)
            ->orderByDesc('elo')
            ->get();
        $numericRank = null;
        foreach ($allStats as $i => $s) {
            if ($s->user_id == $user->id) {
                $numericRank = $i + 1;
                break;
            }
        }

        return view('player', compact('user', 'replays', 'userStats', 'stats', 'rank', 'seasons', 'format', 'seasonId', 'numericRank'));
    }

    public function download($uuid)
    {
        // Find the replay by its UUID
        $replay = Replay::where('replay_id', $uuid)->firstOrFail();

        // Ensure the file exists before trying to download
        $filePath = storage_path('app/public/uploads/' . basename($replay->replay_file));

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found.');
        }

        return response()->download($filePath);
    }
}
