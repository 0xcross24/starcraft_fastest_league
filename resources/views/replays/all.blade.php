<x-app-layout>
    <div class="py-12">
        <div class="max-w-screen-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
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
                                    class="inline-block py-1 px-4 text-sm font-medium focus:outline-none {{ request('format', '2v2') == '2v2' ? 'border-b-4 border-blue-500 text-blue-600 font-bold' : 'border-b-0 text-gray-700 dark:text-gray-300 font-normal' }}">
                                    2v2
                                </a>
                            </li>
                            <li>
                                <a href="?season={{ $seasonId }}&format=3v3"
                                    class="inline-block py-1 px-4 text-sm font-medium focus:outline-none {{ request('format') == '3v3' ? 'border-b-4 border-blue-500 text-blue-600 font-bold' : 'border-b-0 text-gray-700 dark:text-gray-300 font-normal' }}">
                                    3v3
                                </a>
                            </li>
                        </ul>
                        @php
                        $format = request('format', '2v2');
                        $selectedSeason = $seasons->firstWhere('id', $seasonId);
                        @endphp
                        <h1 class="text-lg font-semibold mb-4">
                            @if($selectedSeason)
                            Season {{ $selectedSeason->id }} Replays ({{ strtoupper($format) }})
                            @else
                            All Replays
                            @endif
                        </h1>
                        <div class="mt-4">
                            @if($replays->isEmpty())
                            <p>No replays found for this season and format.</p>
                            @else
                            @foreach($replays->groupBy('replay_id') as $replayId => $groupedReplays)
                            @php
                            $team1 = $groupedReplays->where('team', '1');
                            $team2 = $groupedReplays->where('team', '2');
                            $team1Win = $team1->first() && $team1->first()->winning_team == 1;
                            $team2Win = $team2->first() && $team2->first()->winning_team == 1;
                            @endphp
                            <div class="w-full text-xs text-gray-500 px-3 py-1 border-b border-gray-200 dark:border-gray-700">
                                Replay ID: <span class="font-mono">{{ substr($replayId, 0, 8) }}</span>
                            </div>
                            <div class="flex flex-row border border-gray-200 mb-6 rounded-lg overflow-hidden">
                                <!-- Team 1 -->
                                <div class="w-1/2 border-r border-gray-200">
                                    <div class="p-2 font-bold text-left {{ $team1Win ? 'text-emerald-500' : 'text-red-500' }}">
                                        Team 1 {{ $team1Win ? 'Won' : 'Lost' }}
                                    </div>
                                    <table class="w-full table-fixed border-collapse">
                                        <thead class="bg-gray-200">
                                            <tr>
                                                <th class="px-2 py-1 text-center text-gray-700 text-md w-40">Player</th>
                                                <th class="px-2 py-1 text-center text-gray-700 text-md w-20">Race</th>
                                                <th class="px-2 py-1 text-center text-gray-700 text-md w-20">APM</th>
                                                <th class="px-2 py-1 text-center text-gray-700 text-md w-20">EAPM</th>
                                                <th class="px-2 py-1 text-center text-gray-700 text-md w-20">Points</th>
                                                <th class="px-2 py-1 text-center text-gray-700 text-md w-20">New ELO</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                            static $eloTracker = [];
                                            foreach($team1 as $player) {
                                            $uid = $player->user_id;
                                            if (!isset($eloTracker[$uid])) {
                                            // You may want to fetch the current ELO from the stats table for more accuracy
                                            $eloTracker[$uid] = 1000; // Default starting ELO
                                            }
                                            }
                                            @endphp
                                            @foreach($team1 as $player)
                                            <tr>
                                                <td class="border text-neonBlue font-semibold border-gray-200 text-center px-2 py-1 max-w-xs whitespace-nowrap overflow-hidden text-ellipsis text-md">
                                                    <a href="{{ route('player', ['user' => $player->player_name]) }}" title="{{ $player->player_name }}">{{ $player->player_name }}</a>
                                                </td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-md">{{ $player->race }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-md">{{ $player->apm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-md">{{ $player->eapm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-md">{{ $player->points ?? 'N/A' }}</td>
                                                @php
                                                $uid = $player->user_id;
                                                $eloAfter = $eloTracker[$uid] + ($player->points ?? 0);
                                                $eloTracker[$uid] = $eloAfter;
                                                @endphp
                                                <td class="border border-gray-200 text-center px-2 py-1 text-md">{{ $eloAfter }}</td>
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
                                    <table class="w-full table-fixed border-collapse">
                                        <thead class="bg-gray-200">
                                            <tr>
                                                <th class="px-2 py-1 text-center text-gray-700 text-md w-40">Player</th>
                                                <th class="px-2 py-1 text-center text-gray-700 text-md w-20">Race</th>
                                                <th class="px-2 py-1 text-center text-gray-700 text-md w-20">APM</th>
                                                <th class="px-2 py-1 text-center text-gray-700 text-md w-20">EAPM</th>
                                                <th class="px-2 py-1 text-center text-gray-700 text-md w-20">Points</th>
                                                <th class="px-2 py-1 text-center text-gray-700 text-md w-20">New ELO</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                            foreach($team2 as $player) {
                                            $uid = $player->user_id;
                                            if (!isset($eloTracker[$uid])) {
                                            $eloTracker[$uid] = 1000;
                                            }
                                            }
                                            @endphp
                                            @foreach($team2 as $player)
                                            <tr>
                                                <td class="border text-neonBlue font-semibold border-gray-200 text-center px-2 py-1 text-md">
                                                    <a href="{{ route('player', ['user' => $player->player_name]) }}">{{ $player->player_name }}</a>
                                                </td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-md">{{ $player->race }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-md">{{ $player->apm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-md">{{ $player->eapm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1 text-md">{{ $player->points ?? 'N/A' }}</td>
                                                @php
                                                $uid = $player->user_id;
                                                $eloAfter = $eloTracker[$uid] + ($player->points ?? 0);
                                                $eloTracker[$uid] = $eloAfter;
                                                @endphp
                                                <td class="border border-gray-200 text-center px-2 py-1 text-md">{{ $eloAfter }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- Download link below the tables -->
                            <div class="w-full text-center py-2 mb-6">
                                <a href="{{ route('replay.download', ['uuid' => $replayId]) }}" class="inline-block px-3 py-1 bg-blue-600 text-white text-md rounded hover:bg-blue-700 transition font-semibold">Download Replay</a>
                            </div>
                            @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="/js/season-dropdown.js"></script>

</x-app-layout>
