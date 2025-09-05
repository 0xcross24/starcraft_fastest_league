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
        $request->validate([
            'file' => 'required|file|max:2048',
        ]);

        if (!$request->file('file')->isValid()) {
            return back()->with('error', 'The uploaded file is not valid.');
        }

        if ($request->file('file')->getClientOriginalExtension() !== 'rep') {
            return back()->with('error', 'Unexpected file type.');
        }


        // Get current season for directory
        $currentSeason = \App\Models\Season::where('is_active', 1)->first();
        if (!$currentSeason) {
            return back()->with('error', 'No active season found.');
        }
        $seasonDir = 'uploads/season_' . $currentSeason->id;
        $fileName = time() . '.rep';
        $fileRep = $request->file('file')->storeAs($seasonDir, $fileName, 'public');
        $filePath = storage_path("app/public/$fileRep");

        $scriptPath = '/var/www/html/screp';
        exec("$scriptPath $filePath", $output, $return_var);

        if ($return_var !== 0) {
            return back()->with('error', 'Script execution failed.');
        }

        $jsonOutput = implode("\n", $output);
        $data = json_decode($jsonOutput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->with('error', 'Failed to decode replay JSON.');
        }

        // Enforce replay is not older than 48 hours
        $startTime = $data['Header']['StartTime'] ?? null;
        if ($startTime) {
            $replayTime = strtotime($startTime);
            if ($replayTime < (time() - 48 * 3600)) {
                return back()->with('error', 'Replay is too old (over 48 hours).');
            }
        }

        // Enforce map name starts with 'OP SFL-' or 'SFLClan'

        $mapName = $data['Header']['Map'] ?? '';
        if (!(str_starts_with($mapName, 'OP SFL-') || str_starts_with($mapName, 'SFLClan'))) {
            return back()->with('error', 'Replay must be played on a map starting with OP SFL- or SFLClan.');
        }

        // Generate stable fingerprint
        $fingerprint = $this->generateReplayFingerprint($data);

        // Check for duplicate
        if (Replay::where('hash', $fingerprint)->exists()) {
            return back()->with('error', 'Replay already exists.');
        }

        // Prepare teams data
        $teams = ['Team1' => [], 'Team2' => []];
        $players = $data['Header']['Players'];
        $playerDescs = $data['Computed']['PlayerDescs'];
        $winnerTeam = $data['Computed']['WinnerTeam'] ?? null;
        $startTime = $data['Header']['StartTime'];

        $apms = $eapms = [];
        foreach ($playerDescs as $desc) {
            $apms[$desc['PlayerID']] = $desc['APM'];
            $eapms[$desc['PlayerID']] = $desc['EAPM'];
        }

        foreach ($players as $player) {
            $teamNumber = $player['Team'] ?? null;
            if (!$teamNumber) continue;

            $teamKey = 'Team' . $teamNumber;
            $teams[$teamKey][] = [
                'ID' => $player['ID'],
                'Name' => $player['Name'],
                'StartTime' => $startTime,
                'IsWinner' => ($teamNumber == $winnerTeam),
                'Race' => $player['Race']['Name'],
                'Team' => $teamNumber,
                'APM' => $apms[$player['ID']] ?? 0,
                'EAPM' => $eapms[$player['ID']] ?? 0,
            ];
        }

        return $this->store($teams, $filePath, $fingerprint, $fileName, $data);
    }

    public function store($data, $filePath, $fingerprint, $fileName = null, $rawData = null)
    {
        $playerNames = [];
        $playersData = [];
        $uuid = Str::uuid()->toString();
        $currentSeason = Season::where('is_active', 1)->first();
        if (!$currentSeason) {
            return back()->with('error', 'No active season found.');
        }

        foreach ($data as $team) {
            foreach ($team as $player) {
                if (!empty($player['Name'])) {
                    $playerNames[] = $player['Name'];
                    $playersData[] = ['player' => $player, 'name' => $player['Name']];
                }
            }
        }

        $lowercaseNames = array_map('strtolower', $playerNames);
        $users = User::whereIn(DB::raw('LOWER(player_name)'), $lowercaseNames)->get();
        $registeredNames = $users->pluck('player_name')->map('strtolower')->toArray();

        $unregistered = array_diff($lowercaseNames, $registeredNames);
        if (!empty($unregistered)) {
            return back()->with('error', 'Missing player(s): ' . implode(', ', $unregistered));
        }

        $winners = $losers = [];
        foreach ($playersData as $playerData) {
            $isWinner = $playerData['player']['IsWinner'] ?? false;
            $user = $users->first(fn($u) => strtolower($u->player_name) === strtolower($playerData['name']));
            if (!$user) continue;

            if ($isWinner) $winners[] = $user->id;
            else $losers[] = $user->id;
        }

        $team1Count = count($data['Team1'] ?? []);
        $team2Count = count($data['Team2'] ?? []);
        $format = ($team1Count === 2 && $team2Count === 2) ? '2v2' : (($team1Count === 3 && $team2Count === 3) ? '3v3' : null);

        // Reject invalid team sizes
        if (!$format) {
            return back()->with('error', 'Invalid replay: must be 2v2 or 3v3.');
        }

        // Reject games with no winner (draw)
        $winnerTeam = null;
        foreach ($data as $teamKey => $teamPlayers) {
            foreach ($teamPlayers as $p) {
                if ($p['IsWinner']) {
                    $winnerTeam = $p['Team'];
                    break 2;
                }
            }
        }
        if ($winnerTeam === null) {
            return back()->with('error', 'Replay is a draw. Ignored.');
        }

        // Reject short games (< 2:05)
        $frames = $rawData['Header']['Frames'] ?? 0;
        $frameRate = 24; // Brood War standard
        $gameSeconds = $frames / $frameRate;
        if ($gameSeconds < 125) { // 2 minutes 5 seconds
            return back()->with('error', 'Game too short (<2:05). Ignored.');
        }

        $eloResults = $this->statsController->calculateElo($winners, $losers, $format);

        foreach ($playersData as $playerData) {
            $player = $playerData['player'];
            $user = $users->first(fn($u) => strtolower($u->player_name) === strtolower($playerData['name']));
            if (!$user) continue;

            Replay::create([
                'replay_id' => $uuid,
                'player_name' => $playerData['name'],
                'winning_team' => $player['IsWinner'] ? 1 : 0,
                'start_time' => date('Y-m-d H:i:s', strtotime($player['StartTime'])),
                'team' => $player['Team'],
                'race' => $player['Race'],
                'apm' => $player['APM'],
                'eapm' => $player['EAPM'],
                'hash' => $fingerprint,
                'user_id' => $user->id,
                'replay_file' => $filePath,
                'points' => $eloResults['eloChanges'][$user->id] ?? 0,
                'season_id' => $currentSeason->id,
                'format' => $format,
            ]);
        }

        return redirect()->route('upload.index')->with(['success' => 'Upload successful!', 'file' => $fileName]);
    }

    protected function generateReplayFingerprint(array $data): string
    {
        $header = $data['Header'];

        $stableData = [
            'Host'      => $header['Host'],
            'StartTime' => $header['StartTime'],
        ];

        return hash(
            'sha256',
            json_encode($stableData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function displayPlayer($user)
    {
        $user = User::where('player_name', $user)->first();
        $seasons = Season::all();
        $seasonId = request('season') ?: ($seasons->count() ? $seasons->max('id') : null);
        $format = request('format', '2v2');

        $replayIds = Replay::where('player_name', $user->player_name)
            ->where('season_id', $seasonId)
            ->where('format', $format)
            ->pluck('replay_id');

        $replays = Replay::whereIn('replay_id', $replayIds)
            ->where('season_id', $seasonId)
            ->where('format', $format)
            ->orderByDesc('created_at')
            ->get();

        $user_ids = $replays->pluck('user_id')->unique();
        $statsCollection = Stats::whereIn('user_id', $user_ids)
            ->where('format', $format)
            ->where('season_id', $seasonId)
            ->get();
        $userStats = $statsCollection->keyBy('user_id');

        $stats = Stats::where('user_id', $user->id)
            ->where('season_id', $seasonId)
            ->where('format', $format)
            ->first();
        $rank = $stats ? $this->eloService->getEloGrade($stats->elo) : null;

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
        $replay = Replay::where('replay_id', $uuid)->firstOrFail();
        // Always use the season directory for the file
        $seasonDir = 'uploads/season_' . $replay->season_id;
        $fileName = basename($replay->replay_file);
        $filePath = storage_path('app/public/' . $seasonDir . '/' . $fileName);

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found.');
        }

        return response()->download($filePath);
    }
}
