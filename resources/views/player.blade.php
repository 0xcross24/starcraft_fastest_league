<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @php
                    $format = request('format', '2v2');
                    $selectedSeason = $seasons->firstWhere('id', $seasonId);
                    @endphp
                    <div class="w-full">
                        <ul class="flex border-b border-gray-200 mb-2">
                            @foreach($seasons as $season)
                            <li class="mr-8">
                                <a href="?season={{ $season->id }}&format={{ request('format', '2v2') }}"
                                    class="season-tab inline-block py-2 px-4 text-sm font-medium focus:outline-none {{ $season->id == $seasonId ? 'border-b-4 border-blue-500 text-gray-900 dark:text-white font-bold dark:bg-gray-900' : 'border-b-0 text-gray-700 dark:text-gray-300' }}">
                                    Season {{ $season->id }}
                                </a>
                            </li>
                            @endforeach
                        </ul>
                        <ul class="flex mb-6">
                            <li class="mr-4">
                                <a href="?season={{ $seasonId }}&format=2v2{{ $result ? '&result='.$result : '' }}"
                                    class="inline-block py-1 px-4 text-sm font-medium focus:outline-none {{ request('format', '2v2') == '2v2' ? 'border-b-4 border-blue-500 text-blue-600 font-bold' : 'border-b-0 text-gray-700 dark:text-gray-300 font-bold' }}">
                                    2v2
                                </a>
                            </li>
                            <li>
                                <a href="?season={{ $seasonId }}&format=3v3{{ $result ? '&result='.$result : '' }}"
                                    class="inline-block py-1 px-4 text-sm font-medium focus:outline-none {{ request('format') == '3v3' ? 'border-b-4 border-blue-500 text-blue-600 font-bold' : 'border-b-0 text-gray-700 dark:text-gray-300 font-bold' }}">
                                    3v3
                                </a>
                            </li>
                        </ul>
                        <div class="mb-6 flex flex-wrap items-center gap-4">
                            <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                                Profile: {{ $playerName->name }}
                            </h2>
                            @if($accountNames->count() > 1)
                            <x-username-select :names="$accountNames" :selected="$playerName"
                                :url-for="fn($n) => route('player', ['user' => $n->name, 'season' => $seasonId, 'format' => $format])" />
                            @endif
                        </div>
                        <!-- Player Stats/Ranking for Selected Season/Format -->
                        @php
                        $format = request('format', '2v2');
                        $selectedSeason = $seasons->firstWhere('id', $seasonId);
                        @endphp
                        @if($selectedSeason && $stats)
                        <div class="pb-3 flex flex-wrap items-center justify-between gap-3">
                            <div class=" text-lg font-semibold text-gray-700 dark:text-gray-200">Season {{ $selectedSeason->id }} ({{ strtoupper($format) }})</div>
                            <div class="flex flex-wrap gap-2">
                                {{-- This format (Wk`Cross-_s2_3v3.zip) and the whole season (Wk`Cross-_s2.zip) --}}
                                @if($games->total() > 0)
                                <x-zip-download-button :season="$selectedSeason->id" :format="$format" :player="$playerName->name" :count="$games->total()" :label="'Download ' . $format" />
                                @endif
                                @if($seasonGameCount > 0)
                                <x-zip-download-button :season="$selectedSeason->id" :player="$playerName->name" :count="$seasonGameCount" label="Download all formats" />
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-start gap-8 bg-gray-100 dark:bg-gray-700 rounded-lg p-6 mb-10">
                            <div>
                                <div class="text-lg font-semibold text-gray-700 dark:text-gray-200">Rank</div>
                                <div class="text-lg font-semibold text-emerald-600 dark:text-emerald-400 mt-1">#{{ $numericRank ?? 'N/A' }}</div>
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-gray-700 dark:text-gray-200">Grade</div>
                                <x-elo-grade :elo="$stats->elo ?? 0" class="text-lg font-semibold mt-1 block" />
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
                            {{-- All / Wins / Lost: every game this player lost, and to whom, in one click --}}
                            <ul class="flex gap-2 mb-4 text-sm">
                                @foreach(['all' => 'All', 'win' => 'Wins', 'lost' => 'Lost'] as $key => $label)
                                @php
                                $n = match ($key) { 'all' => $resultCounts->sum(), 'win' => $resultCounts[1] ?? 0, 'lost' => $resultCounts[0] ?? 0 };
                                $active = ($result ?? 'all') === $key;
                                @endphp
                                <li>
                                    <a href="?{{ http_build_query(array_filter(['season' => $seasonId, 'format' => $format, 'result' => $key === 'all' ? null : $key])) }}"
                                        class="inline-block rounded px-3 py-1 font-semibold {{ $active ? ['all' => 'bg-blue-600', 'win' => 'bg-emerald-600', 'lost' => 'bg-red-600'][$key] . ' text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}">
                                        {{ $label }} ({{ $n }})
                                    </a>
                                </li>
                                @endforeach
                            </ul>
                            @if($format === '2v2')
                            @php
                            $seasonReplays2v2 = $replays->where('season_id', $selectedSeason->id)->where('format', '2v2')->sortBy('created_at');
                            @endphp
                            @if($seasonReplays2v2->isEmpty())
                            <p>No 2v2 replays found for this season.</p>
                            @else
                            @php
                            $replayGroupsDesc = $seasonReplays2v2->groupBy('replay_id')->sortByDesc(function($groupedReplays) { return $groupedReplays->first()->created_at; });
                            @endphp
                            @foreach($replayGroupsDesc as $replayId => $groupedReplays)
                            @php
                            // From this profile's side: its team on the left, opponents on the right
                            $ownTeam = optional($groupedReplays->firstWhere('player_name_id', $playerName->id))->team ?? '1';
                            $team1 = $groupedReplays->where('team', $ownTeam);
                            $team2 = $groupedReplays->where('team', '!=', $ownTeam);
                            $team1Win = $team1->first() && $team1->first()->winning_team == 1;
                            $team2Win = $team2->first() && $team2->first()->winning_team == 1;
                            @endphp
                            <x-profile-game-result :replay-id="$replayId" :won="$team1Win"
                                :own="$groupedReplays->firstWhere('player_name_id', $playerName->id)" />
                            <div class="flex flex-col xl:flex-row gap-3 xl:gap-0 mb-6">
                                <!-- This profile's team -->
                                <div class="w-full xl:w-1/2 overflow-x-auto">
                                    <table class="w-full xl:table-fixed border-collapse border border-gray-200">
                                        <thead class="bg-gray-200 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-40">Player</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20">Race</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20">APM</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20 hidden sm:table-cell">EAPM</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20">Points</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20">New ELO</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                            // Track ELO for each player in this team
                                            @endphp
                                            @foreach($team1 as $player)
                                            <tr>
                                                <td class="border text-neonBlue font-semibold border-gray-200 text-center px-2 py-1 max-w-xs whitespace-nowrap overflow-hidden text-ellipsis text-sm">
                                                    @if($player->player_name_id === $playerName->id)<span class="font-bold text-gray-100" title="{{ $player->player_name }}">{{ $player->player_name }}</span>@else<a href="{{ route('player', ['user' => $player->player_name]) }}" title="{{ $player->player_name }}">{{ $player->player_name }}</a>@endif
                                                </td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->race }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->apm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm hidden sm:table-cell">{{ $player->eapm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->points ?? 'N/A' }}</td>
                                                @php
                                                $eloAfter = $player->elo_after;
                                                @endphp
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $eloAfter ?? '—' }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Other team -->
                                <div class="w-full xl:w-1/2 overflow-x-auto">
                                    <table class="w-full xl:table-fixed border-collapse border border-gray-200">
                                        <thead class="bg-gray-200 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-40">Player</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20">Race</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20">APM</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20 hidden sm:table-cell">EAPM</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20">Points</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20">New ELO</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                            @endphp
                                            @foreach($team2 as $player)
                                            <tr>
                                                <td class="border text-neonBlue font-semibold border-gray-200 text-center px-2 py-1 max-w-xs whitespace-nowrap overflow-hidden text-ellipsis text-sm">
                                                    @if($player->player_name_id === $playerName->id)<span class="font-bold text-gray-100" title="{{ $player->player_name }}">{{ $player->player_name }}</span>@else<a href="{{ route('player', ['user' => $player->player_name]) }}" title="{{ $player->player_name }}">{{ $player->player_name }}</a>@endif
                                                </td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->race }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->apm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm hidden sm:table-cell">{{ $player->eapm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->points ?? 'N/A' }}</td>
                                                @php
                                                $eloAfter = $player->elo_after;
                                                @endphp
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $eloAfter ?? '—' }}</td>
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
                            $replayGroupsDesc3v3 = $seasonReplays3v3->groupBy('replay_id')->sortByDesc(function($groupedReplays) { return $groupedReplays->first()->created_at; });
                            @endphp
                            @foreach($replayGroupsDesc3v3 as $replayId => $groupedReplays)
                            @php
                            // From this profile's side: its team on the left, opponents on the right
                            $ownTeam = optional($groupedReplays->firstWhere('player_name_id', $playerName->id))->team ?? '1';
                            $team1 = $groupedReplays->where('team', $ownTeam);
                            $team2 = $groupedReplays->where('team', '!=', $ownTeam);
                            $team1Win = $team1->first() && $team1->first()->winning_team == 1;
                            $team2Win = $team2->first() && $team2->first()->winning_team == 1;
                            @endphp
                            <x-profile-game-result :replay-id="$replayId" :won="$team1Win"
                                :own="$groupedReplays->firstWhere('player_name_id', $playerName->id)" />
                            <div class="flex flex-col xl:flex-row gap-3 xl:gap-0 mb-6">
                                <!-- This profile's team -->
                                <div class="w-full xl:w-1/2 overflow-x-auto">
                                    <table class="w-full xl:table-fixed border-collapse border border-gray-200">
                                        <thead class="bg-gray-200 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-40">Player</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20">Race</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20">APM</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20 hidden sm:table-cell">EAPM</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20">Points</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20">New ELO</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                            @endphp
                                            @foreach($team1 as $player)
                                            <tr>
                                                <td class="border text-neonBlue font-semibold border-gray-200 text-center px-2 py-1 max-w-xs whitespace-nowrap overflow-hidden text-ellipsis text-sm">
                                                    @if($player->player_name_id === $playerName->id)<span class="font-bold text-gray-100" title="{{ $player->player_name }}">{{ $player->player_name }}</span>@else<a href="{{ route('player', ['user' => $player->player_name]) }}" title="{{ $player->player_name }}">{{ $player->player_name }}</a>@endif
                                                </td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->race }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->apm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm hidden sm:table-cell">{{ $player->eapm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->points ?? 'N/A' }}</td>
                                                @php
                                                $eloAfter = $player->elo_after;
                                                @endphp
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $eloAfter ?? '—' }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Other team -->
                                <div class="w-full xl:w-1/2 overflow-x-auto">
                                    <table class="w-full xl:table-fixed border-collapse border border-gray-200">
                                        <thead class="bg-gray-200 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-40">Player</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20">Race</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20">APM</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20 hidden sm:table-cell">EAPM</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20">Points</th>
                                                <th class="px-2 py-1 text-center text-gray-200 text-sm xl:w-20">New ELO</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                            @endphp
                                            @foreach($team2 as $player)
                                            <tr>
                                                <td class="border text-neonBlue font-semibold border-gray-200 text-center px-2 py-1 max-w-xs whitespace-nowrap overflow-hidden text-ellipsis text-sm">
                                                    @if($player->player_name_id === $playerName->id)<span class="font-bold text-gray-100" title="{{ $player->player_name }}">{{ $player->player_name }}</span>@else<a href="{{ route('player', ['user' => $player->player_name]) }}" title="{{ $player->player_name }}">{{ $player->player_name }}</a>@endif
                                                </td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->race }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->apm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm hidden sm:table-cell">{{ $player->eapm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $player->points ?? 'N/A' }}</td>
                                                @php
                                                $eloAfter = $player->elo_after;
                                                @endphp
                                                <td class="border border-gray-200 text-center px-2 py-1 text-sm">{{ $eloAfter ?? '—' }}</td>
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
                            <div class="mt-6">{{ $games->links() }}</div>
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
