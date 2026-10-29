<?php

namespace App\Http\Controllers;

use App\Models\PlayerName;
use App\Models\Replay;
use App\Models\User;
use App\Models\Stats;
use App\Support\GameFilter;
use App\Models\Season;
use App\Services\EloService;
use App\Services\ReplayParser;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

    public const MAX_FILES_PER_UPLOAD = 20;

    /**
     * Accepts one replay (`file`) or a batch (`files[]`). Every file is checked
     * on its own; the ones that pass are rated oldest game first, whatever
     * order they were picked in, because each game's points depend on the
     * ratings left by the games before it.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required_without:files|file|max:2048',
            'files' => 'required_without:file|array|max:' . self::MAX_FILES_PER_UPLOAD,
            'files.*' => 'file|max:2048',
        ]);

        $uploads = $request->file('files') ?? [$request->file('file')];

        $results = [];
        $parsed = [];
        foreach ($uploads as $i => $file) {
            $replay = $this->parseUpload($file);
            if (isset($replay['error'])) {
                $results[$i] = ['file' => $file->getClientOriginalName(), 'ok' => false, 'message' => $replay['error']];
            } else {
                $parsed[$i] = $replay;
            }
        }

        uasort($parsed, fn($a, $b) => $a['startedAt'] <=> $b['startedAt']);
        foreach ($parsed as $i => $replay) {
            $results[$i] = ['file' => $uploads[$i]->getClientOriginalName()] + $this->uploadGame($uploads[$i], $replay);
        }

        // A single replay keeps the original one-line messages.
        if (count($uploads) === 1) {
            $result = reset($results);
            return $result['ok']
                ? redirect()->route('upload.index')->with(['success' => 'Upload successful!', 'file' => $result['stored']])
                : back()->with('error', $result['message']);
        }

        // Listed in the order the games were rated, failures last.
        $ordered = array_merge(
            array_values(array_intersect_key($results, $parsed)),
            array_values(array_diff_key($results, $parsed)),
        );
        $uploaded = count(array_filter($ordered, fn($r) => $r['ok']));

        return redirect()->route('upload.index')->with([
            'results' => $ordered,
            'summary' => "{$uploaded} of " . count($ordered) . ' replays uploaded.',
        ]);
    }

    /**
     * Run screp and the checks that need nothing but the file itself.
     * Returns ['error' => reason] or the parsed replay.
     */
    private function parseUpload(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            return ['error' => 'The uploaded file is not valid.'];
        }

        if ($file->getClientOriginalExtension() !== 'rep') {
            return ['error' => 'Unexpected file type.'];
        }

        // Run screp on the original temp file before storing permanently
        $parsed = app(ReplayParser::class)->parse($file->getPathname());
        if (isset($parsed['error'])) {
            return ['error' => $parsed['error']];
        }
        $data = $parsed['data'];

        // Enforce replay is not older than 48 hours
        $startTime = $data['Header']['StartTime'] ?? null;
        $startedAt = $startTime ? strtotime($startTime) : 0;
        if ($startTime && $startedAt < (time() - 48 * 3600)) {
            return ['error' => 'Replay is too old (over 48 hours).'];
        }

        // Enforce exact map name match
        $mapName = $data['Header']['Map'] ?? '';
        $mapName = preg_replace('/[[:^print:]]/', '', $mapName); // Remove non-printable chars
        if (!in_array($mapName, ['OP SFL-', 'SFLClan', 'Clan Wk` Styler(Ob)'], true)) {
            return ['error' => 'Replay must be played on OP SFL-, SFLClan, or Clan Wk` Styler(Ob) exactly. You can find the map in your Profile page'];
        }

        return ['data' => $data, 'fingerprint' => $this->generateReplayFingerprint($data), 'startedAt' => $startedAt];
    }

    /**
     * Store and rate one parsed replay. Returns ['ok', 'message', 'stored'?].
     */
    private function uploadGame(UploadedFile $file, array $replay): array
    {
        $data = $replay['data'];
        $fingerprint = $replay['fingerprint'];

        // Check for duplicate (also catches the same game twice in one batch)
        if (Replay::alreadyUploaded($fingerprint)) {
            return ['ok' => false, 'message' => 'Replay already exists.'];
        }

        $currentSeason = \App\Models\Season::where('is_active', 1)->first();
        if (!$currentSeason) {
            return ['ok' => false, 'message' => 'No active season found.'];
        }
        $seasonDir = 'uploads/season_' . $currentSeason->id;
        // Unique per file: a batch stores several within the same second.
        $fileName = time() . '_' . Str::lower(Str::random(6)) . '.rep';
        $fileRep = $file->storeAs($seasonDir, $fileName, 'public');
        if (!$fileRep) {
            Log::error('Replay upload storage failed', [
                'season_dir' => $seasonDir,
                'file_name' => $fileName,
                'disk' => 'public',
            ]);
            return ['ok' => false, 'message' => 'Failed to store uploaded replay file.'];
        }
        $filePath = storage_path("app/public/$fileRep");

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

        $result = $this->storeGame($teams, $filePath, $fingerprint, $fileName, $data, $fileRep);
        if (!$result['ok']) {
            return ['ok' => false, 'message' => $result['error']];
        }

        $points = collect($result['points'])->map(fn($p, $name) => $name . ' ' . ($p >= 0 ? '+' : '') . $p)->implode(', ');

        return ['ok' => true, 'message' => 'Uploaded: ' . $points, 'stored' => $fileName];
    }

    public function store($data, $filePath, $fingerprint, $fileName = null, $rawData = null, $fileRep = null)
    {
        $result = $this->storeGame($data, $filePath, $fingerprint, $fileName, $rawData, $fileRep);

        return $result['ok']
            ? redirect()->route('upload.index')->with(['success' => 'Upload successful!', 'file' => $fileName])
            : back()->with('error', $result['error']);
    }

    /**
     * Rate and record one parsed game. Returns ['ok' => true, 'points' => [name => change]]
     * or ['ok' => false, 'error' => reason]; on failure the stored file is removed.
     */
    protected function storeGame($data, $filePath, $fingerprint, $fileName = null, $rawData = null, $fileRep = null): array
    {
        $cleanup = function () use ($fileRep) {
            if ($fileRep) {
                Storage::disk('public')->delete($fileRep);
            }
        };

        $playerNames = [];
        $playersData = [];
        $uuid = Str::uuid()->toString();
        $currentSeason = Season::where('is_active', 1)->first();
        if (!$currentSeason) {
            $cleanup();
            return ['ok' => false, 'error' => 'No active season found.'];
        }

        foreach ($data as $team) {
            foreach ($team as $player) {
                if (!empty($player['Name'])) {
                    $playerNames[] = $player['Name'];
                    $playersData[] = ['player' => $player, 'name' => $player['Name']];
                }
            }
        }

        // Each replay name must be one of a registered account's current names.
        $lowercaseNames = array_map('strtolower', $playerNames);
        $names = PlayerName::active()->named(...$playerNames)->with('user')->get();
        $registeredNames = $names->pluck('name')->map('strtolower')->toArray();

        $unregistered = array_diff($lowercaseNames, $registeredNames);
        if (!empty($unregistered)) {
            $cleanup();
            return ['ok' => false, 'error' => 'Missing player(s): ' . implode(', ', $unregistered)];
        }

        // One account playing under two of its names in the same game would
        // be rating itself.
        $sharedAccounts = $names->groupBy('user_id')->filter(fn($group) => $group->count() > 1);
        if ($sharedAccounts->isNotEmpty()) {
            $cleanup();
            return ['ok' => false, 'error' => 'These names belong to the same account: ' . $sharedAccounts->flatten()->pluck('name')->implode(', ')];
        }

        $nameFor = fn($replayName) => $names->first(fn($n) => strtolower($n->name) === strtolower($replayName));

        $winners = $losers = [];
        foreach ($playersData as $playerData) {
            $isWinner = $playerData['player']['IsWinner'] ?? false;
            $playerName = $nameFor($playerData['name']);
            if (!$playerName) continue;

            if ($isWinner) $winners[] = $playerName->user_id;
            else $losers[] = $playerName->user_id;
        }

        $team1Count = count($data['Team1'] ?? []);
        $team2Count = count($data['Team2'] ?? []);
        $format = ($team1Count === 2 && $team2Count === 2) ? '2v2' : (($team1Count === 3 && $team2Count === 3) ? '3v3' : null);

        // Reject invalid team sizes
        if (!$format) {
            $cleanup();
            return ['ok' => false, 'error' => 'Invalid replay: must be 2v2 or 3v3.'];
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
            $cleanup();
            return ['ok' => false, 'error' => 'Replay is a draw. Ignored.'];
        }

        // Reject short games (< 2:05)
        $frames = $rawData['Header']['Frames'] ?? 0;
        $frameRate = 24; // Brood War standard
        $gameSeconds = $frames / $frameRate;
        if ($gameSeconds < 125) { // 2 minutes 5 seconds
            $cleanup();
            return ['ok' => false, 'error' => 'Game too short (<2:05). Ignored.'];
        }

        // An account registered mid-season may not have a row for this season yet.
        foreach ($names as $playerName) {
            $playerName->user->ensureStats($currentSeason);
        }

        $eloResults = $this->statsController->calculateElo($winners, $losers, $format);

        // Each account's ELO after this game, stored on its replay row
        $eloAfter = Stats::where('season_id', $currentSeason->id)
            ->where('format', $format)
            ->whereIn('user_id', $names->pluck('user_id'))
            ->pluck('elo', 'user_id');

        foreach ($playersData as $playerData) {
            $player = $playerData['player'];
            $playerName = $nameFor($playerData['name']);
            if (!$playerName) continue;

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
                'user_id' => $playerName->user_id,
                'player_name_id' => $playerName->id,
                'replay_file' => $filePath,
                'points' => $eloResults['eloChanges'][$playerName->user_id] ?? 0,
                'elo_after' => $eloAfter[$playerName->user_id] ?? null,
                'season_id' => $currentSeason->id,
                'format' => $format,
            ]);
        }

        return [
            'ok' => true,
            'points' => $names->mapWithKeys(fn($n) => [$n->name => (int) round($eloResults['eloChanges'][$n->user_id] ?? 0)])->all(),
        ];
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

    public function displayPlayer($name)
    {
        // Any of an account's names (retired ones too) opens the account's
        // page, which lives at its main name.
        $playerName = PlayerName::where('name', $name)->firstOrFail();
        $user = $playerName->user;
        if ($playerName->name !== $user->player_name) {
            return redirect()->route('player', ['user' => $user->player_name] + request()->query());
        }
        $accountNames = $user->playerNames;
        $seasons = Season::all();
        $seasonId = request('season') ?: ($seasons->count() ? $seasons->max('id') : null);
        $format = request('format', '2v2');

        // Optional filter: only the games this account won, or only the ones it lost
        $result = in_array(request('result'), ['win', 'lost'], true) ? request('result') : null;
        // Date / race filters (race = the race this account played); the page is already one player
        $filter = GameFilter::fromRequest(request(), withPlayer: false);
        $ownGames = fn() => $filter->apply(Replay::valid()->where('user_id', $user->id)
            ->where('season_id', $seasonId)
            ->where('format', $format));

        $games = Replay::gamesPage(
            $ownGames()->when($result, fn($q) => $q->where('winning_team', $result === 'win' ? 1 : 0))
        );
        $resultCounts = $ownGames()
            ->selectRaw('winning_team, count(distinct replay_id) as games')
            ->groupBy('winning_team')
            ->pluck('games', 'winning_team');

        $replays = Replay::whereIn('replay_id', $games->pluck('replay_id'))
            ->orderByDesc('created_at')
            ->get();

        // The profile's zip download covers the whole season, both formats
        $seasonGameCount = $filter->apply(Replay::valid()
            ->where('user_id', $user->id)
            ->where('season_id', $seasonId))
            ->distinct()
            ->count('replay_id');

        $stats = Stats::where('user_id', $user->id)
            ->where('season_id', $seasonId)
            ->where('format', $format)
            ->first();
        $rank = $stats ? $this->eloService->getEloGrade($stats->elo) : null;
        $numericRank = $stats?->position();

        return view('player', compact('user', 'playerName', 'accountNames', 'replays', 'games', 'seasonGameCount', 'result', 'resultCounts', 'stats', 'rank', 'seasons', 'format', 'seasonId', 'numericRank', 'filter'));
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
