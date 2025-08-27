<x-app-layout>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @php
                    $format = request('format', '2v2');
                    $selectedSeason = $seasons->firstWhere('id', $seasonId);
                    @endphp
                    <div class="container w-full">
                        <ul class="flex border-b border-gray-200 mb-2">
                            @foreach($seasons as $season)
                            <li class="mr-8">
                                <a href="?season={{ $season->id }}&format={{ request('format', '2v2') }}"
                                    class="season-tab inline-block py-2 px-4 text-sm font-medium focus:outline-none {{ $season->id == $seasonId ? 'border-b-4 border-blue-500 text-white font-bold dark:bg-gray-900' : 'border-b-0 text-gray-700 dark:text-gray-300' }}">
                                    Season {{ $season->id }}
                                </a>
                            </li>
                            @endforeach
                        </ul>
                        <ul class="flex mb-6">
                            <li class="mr-4">
                                <a href="?season={{ $seasonId }}&format=2v2"
                                    class="inline-block py-1 px-4 text-sm font-medium focus:outline-none {{ request('format', '2v2') == '2v2' ? 'border-b-4 border-blue-500 text-blue-600 font-bold' : 'border-b-0 text-gray-700 dark:text-gray-300 font-bold' }}">
                                    2v2
                                </a>
                            </li>
                            <li>
                                <a href="?season={{ $seasonId }}&format=3v3"
                                    class="inline-block py-1 px-4 text-sm font-medium focus:outline-none {{ request('format') == '3v3' ? 'border-b-4 border-blue-500 text-blue-600 font-bold' : 'border-b-0 text-gray-700 dark:text-gray-300 font-bold' }}">
                                    3v3
                                </a>
                            </li>
                        </ul>
                        <div class="mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                                Profile: {{ $user->player_name }}
                            </h2>
                        </div>
                        <!-- Player Stats/Ranking for Selected Season/Format -->
                        @php
                        $format = request('format', '2v2');
                        $selectedSeason = $seasons->firstWhere('id', $seasonId);
                        @endphp
                        @if($selectedSeason && $stats)
                        <div class="pb-3">
                            <div class=" text-lg font-semibold text-gray-700 dark:text-gray-200">Season {{ $selectedSeason->id }} ({{ strtoupper($format) }})</div>
                        </div>
                        <div class="flex flex-wrap items-center justify-start gap-8 bg-gray-100 dark:bg-gray-700 rounded-lg p-6 mb-10">
                            <div>
                                <div class="text-lg font-semibold text-gray-700 dark:text-gray-200">Rank</div>
                                <div class="text-lg font-semibold text-emerald-600 dark:text-emerald-400 mt-1">#{{ $numericRank ?? 'N/A' }}</div>
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-gray-700 dark:text-gray-200">Grade</div>
                                @php
                                $eloService = app('App\\Services\\EloService');
                                $grade = $eloService->getEloGrade($stats->elo ?? 0);
                                $color = $eloService->getGradeColorClass($grade);
                                @endphp
                                <div class="text-lg font-semibold mt-1 {{ $grade === 'S' ? 'text-neonGold' : $color }}">
                                    {{ $grade }}
                                </div>
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-gray-700 dark:text-gray-200">Won</div>
                                <div class="text-lg font-semibold text-green-400 dark:text-green-400 mt-1">{{ $stats->wins ?? 0 }}</div>
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-gray-700 dark:text-gray-200">Lost</div>
                                <div class="text-lg font-semibold text-red-500 dark:text-red-500 mt-1">{{ $stats->losses ?? 0 }}</div>
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-gray-700 dark:text-gray-200">ELO</div>
                                <div class="text-lg font-semibold text-emerald-600 dark:text-emerald-400 mt-1">{{ $stats->elo ?? 'N/A' }}</div>
                            </div>
                        </div>
                        @endif
                        @if($selectedSeason)
                        <div id="season-{{ $selectedSeason->id }}" class="season-ranking block mt-10">
                            @if($format === '2v2')
                            @php
                            $seasonReplays2v2 = $replays->where('season_id', $selectedSeason->id)->where('format', '2v2')->sortBy('created_at');
                            @endphp
                            @if($seasonReplays2v2->isEmpty())
                            <p>No 2v2 replays found for this season.</p>
                            @else
                            @php
                            // Calculate ELO progression in ascending order, store for each replay
                            $eloProgression = [];
                            $eloTracker = [];
                            $replayGroupsAsc = $seasonReplays2v2->groupBy('replay_id')->sortBy(function($groupedReplays) { return $groupedReplays->first()->created_at; });
                            foreach ($replayGroupsAsc as $replayIdAsc => $groupedReplaysAsc) {
                            foreach (['1', '2'] as $teamNum) {
                            $team = $groupedReplaysAsc->where('team', $teamNum);
                            foreach ($team as $player) {
                            $uid = $player->user_id;
                            if (!isset($eloTracker[$uid])) {
                            $eloTracker[$uid] = 1000;
                            }
                            $eloTracker[$uid] += ($player->points ?? 0);
                            $eloProgression[$replayIdAsc][$uid] = $eloTracker[$uid];
                            }
                            }
                            }
                            $replayGroupsDesc = $seasonReplays2v2->groupBy('replay_id')->sortByDesc(function($groupedReplays) { return $groupedReplays->first()->created_at; });
                            @endphp
                            @foreach($replayGroupsDesc as $replayId => $groupedReplays)
                            @php
                            $team1 = $groupedReplays->where('team', '1');
                            $team2 = $groupedReplays->where('team', '2');
                            $team1Win = $team1->first() && $team1->first()->winning_team == 1;
                            $team2Win = $team2->first() && $team2->first()->winning_team == 1;
                            @endphp
                            <div class="w-full text-xs text-gray-300 px-3 py-1 border-b border-gray-200 dark:border-gray-700">
                                @php
                                $firstReplay = $groupedReplays->first();
                                $uploadTime = $firstReplay?->created_at;
                                @endphp
                                Replay ID: <span class="font-mono">{{ substr($replayId, 0, 8) }}</span>
                                <span class="ml-2 text-gray-300">Upload: {{ $uploadTime ? (\Carbon\Carbon::parse($uploadTime)->format('Y-m-d H:i')) : '' }}</span>
                            </div>
                            <div class="flex flex-row mb-6 overflow-hidden">
                                <!-- Team 1 -->
                                <div class="w-1/2">
                                    <div class="p-2 font-bold text-left {{ $team1Win ? 'text-emerald-500' : 'text-red-500' }}">
                                        Team 1 {{ $team1Win ? 'Won' : 'Lost' }}
                                    </div>
                                    <table class="w-full table-fixed border-collapse border border-gray-200">
                                        <thead class="bg-gray-200 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-40">Player</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">Race</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">APM</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">EAPM</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">Points</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">New ELO</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                            // Track ELO for each player in this team
                                            @endphp
                                            @foreach($team1 as $player)
                                            <tr>
                                                <td class="border text-neonBlue font-semibold border-gray-200 text-center px-2 py-1 max-w-xs whitespace-nowrap overflow-hidden text-ellipsis text-sm">
                                                    <a href="{{ route('player', ['user' => $player->player_name]) }}" title="{{ $player->player_name }}">{{ $player->player_name }}</a>
                                                </td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->race }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->apm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->eapm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->points ?? 'N/A' }}</td>
                                                @php
                                                $uid = $player->user_id;
                                                $eloAfter = $eloProgression[$replayId][$uid] ?? 1000;
                                                @endphp
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $eloAfter }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Team 2 -->
                                <div class="w-1/2">
                                    <div class="p-2 font-bold text-left {{ $team2Win ? 'text-emerald-500' : 'text-red-500' }}">
                                        Team 2 {{ $team2Win ? 'Won' : 'Lost' }}
                                    </div>
                                    <table class="w-full table-fixed border-collapse border border-gray-200">
                                        <thead class="bg-gray-200 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-40">Player</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">Race</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">APM</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">EAPM</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">Points</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">New ELO</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                            @endphp
                                            @foreach($team2 as $player)
                                            <tr>
                                                <td class="border text-neonBlue font-semibold border-gray-200 text-center px-2 py-1 max-w-xs whitespace-nowrap overflow-hidden text-ellipsis text-sm">
                                                    <a href="{{ route('player', ['user' => $player->player_name]) }}" title="{{ $player->player_name }}">{{ $player->player_name }}</a>
                                                </td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->race }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->apm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->eapm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->points ?? 'N/A' }}</td>
                                                @php
                                                $uid = $player->user_id;
                                                $eloAfter = $eloProgression[$replayId][$uid] ?? 1000;
                                                @endphp
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $eloAfter }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- Download link below the tables -->
                            <div class="w-full text-center py-2 mb-6">
                                <a href="{{ route('replay.download', ['uuid' => $replayId]) }}" class="inline-block px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition font-semibold">Download Replay</a>
                            </div>
                            @endforeach
                            @endif
                            @elseif($format === '3v3')
                            @php
                            $seasonReplays3v3 = $replays->where('season_id', $selectedSeason->id)->where('format', '3v3')->sortBy('created_at');
                            @endphp
                            @if($seasonReplays3v3->isEmpty())
                            <p>No 3v3 replays found for this season.</p>
                            @else
                            @php
                            // Calculate ELO progression for 3v3 in ascending order, store for each replay
                            $eloProgression3v3 = [];
                            $eloTracker3v3 = [];
                            $replayGroupsAsc3v3 = $seasonReplays3v3->groupBy('replay_id')->sortBy(function($groupedReplays) { return $groupedReplays->first()->created_at; });
                            foreach ($replayGroupsAsc3v3 as $replayIdAsc => $groupedReplaysAsc) {
                            foreach (['1', '2'] as $teamNum) {
                            $team = $groupedReplaysAsc->where('team', $teamNum);
                            foreach ($team as $player) {
                            $uid = $player->user_id;
                            if (!isset($eloTracker3v3[$uid])) {
                            $eloTracker3v3[$uid] = 1000;
                            }
                            $eloTracker3v3[$uid] += ($player->points ?? 0);
                            $eloProgression3v3[$replayIdAsc][$uid] = $eloTracker3v3[$uid];
                            }
                            }
                            }
                            $replayGroupsDesc3v3 = $seasonReplays3v3->groupBy('replay_id')->sortByDesc(function($groupedReplays) { return $groupedReplays->first()->created_at; });
                            @endphp
                            @foreach($replayGroupsDesc3v3 as $replayId => $groupedReplays)
                            @php
                            $team1 = $groupedReplays->where('team', '1');
                            $team2 = $groupedReplays->where('team', '2');
                            $team1Win = $team1->first() && $team1->first()->winning_team == 1;
                            $team2Win = $team2->first() && $team2->first()->winning_team == 1;
                            @endphp
                            <div class="w-full text-xs text-gray-300 px-3 py-1 border-b border-gray-200 dark:border-gray-700">
                                @php
                                $firstReplay = $groupedReplays->first();
                                $timestamp = $firstReplay?->start_time ?? $firstReplay?->created_at;
                                @endphp
                                Replay ID: <span class="font-mono">{{ substr($replayId, 0, 8) }}</span>
                                <span class="ml-2 text-gray-300">{{ $timestamp ? (\Carbon\Carbon::parse($timestamp)->format('Y-m-d H:i')) : '' }}</span>
                            </div>
                            <div class="flex flex-row mb-6 overflow-hidden">
                                <!-- Team 1 -->
                                <div class="w-1/2">
                                    <div class="p-2 font-bold text-left {{ $team1Win ? 'text-emerald-500' : 'text-red-500' }}">
                                        Team 1 {{ $team1Win ? 'Won' : 'Lost' }}
                                    </div>
                                    <table class="w-full table-fixed border-collapse border border-gray-200">
                                        <thead class="bg-gray-200 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-40">Player</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">Race</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">APM</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">EAPM</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">Points</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">New ELO</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                            @endphp
                                            @foreach($team1 as $player)
                                            <tr>
                                                <td class="border text-neonBlue font-semibold border-gray-200 text-center px-2 py-1 max-w-xs whitespace-nowrap overflow-hidden text-ellipsis text-sm">
                                                    <a href="{{ route('player', ['user' => $player->player_name]) }}" title="{{ $player->player_name }}">{{ $player->player_name }}</a>
                                                </td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->race }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->apm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->eapm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->points ?? 'N/A' }}</td>
                                                @php
                                                $uid = $player->user_id;
                                                $eloAfter = $eloProgression3v3[$replayId][$uid] ?? 1000;
                                                @endphp
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $eloAfter }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Team 2 -->
                                <div class="w-1/2">
                                    <div class="p-2 font-bold text-left {{ $team2Win ? 'text-emerald-500' : 'text-red-500' }}">
                                        Team 2 {{ $team2Win ? 'Won' : 'Lost' }}
                                    </div>
                                    <table class="w-full table-fixed border-collapse border border-gray-200">
                                        <thead class="bg-gray-200 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-40">Player</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">Race</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">APM</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">EAPM</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">Points</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm w-20">New ELO</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                            @endphp
                                            @foreach($team2 as $player)
                                            <tr>
                                                <td class="border text-neonBlue font-semibold border-gray-200 text-center px-2 py-1 max-w-xs whitespace-nowrap overflow-hidden text-ellipsis text-sm">
                                                    <a href="{{ route('player', ['user' => $player->player_name]) }}" title="{{ $player->player_name }}">{{ $player->player_name }}</a>
                                                </td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->race }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->apm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->eapm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->points ?? 'N/A' }}</td>
                                                @php
                                                $uid = $player->user_id;
                                                $eloAfter = $eloProgression3v3[$replayId][$uid] ?? 1000;
                                                @endphp
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $eloAfter }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- Download link below the tables -->
                            <div class="w-full text-center py-2 mb-6">
                                <a href="{{ route('replay.download', ['uuid' => $replayId]) }}" class="inline-block px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition font-semibold">Download Replay</a>
                            </div>
                            @endforeach
                            @endif
                            @endif
                        </div>
                        @endif

                        </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    </div>

    <script src="/js/season-dropdown.js"></script>
    <!-- Neon Gold CSS for S grade -->
    <style>
        .text-neonGold {
            color: #FFD700;
            text-shadow: 0 0 8px #FFD700, 0 0 16px #FFD700;
        }
    </style>

</x-app-layout>
