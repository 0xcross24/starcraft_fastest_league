<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h1 class="font-semibold text-3xl text-gray-800 dark:text-gray-200 leading-tight mb-6">
                        All Replays
                    </h1>
                    <div class="container w-full">
                        <ul class="flex border-b border-gray-200 mb-6">
                            @foreach($seasons as $season)
                            <li class="mr-8 relative season-dropdown">
                                <button class="season-tab inline-block py-2 px-4 text-sm font-medium border-b-4 focus:outline-none {{ $season->id == $seasonId ? 'border-blue-500 text-white font-bold dark:bg-gray-900 font-bold' : 'border-transparent text-gray-700 dark:text-gray-300' }}">
                                    Season {{ $season->id }}
                                </button>
                                <div class="dropdown-menu absolute px-4 left-0 mt-2 min-w-full bg-white dark:bg-gray-800 border border-gray-200 rounded shadow-lg z-50 hidden" style="pointer-events: auto;">
                                    <a href="?season={{ $season->id }}&format=2v2" class="block px-4 w-full text-center py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 {{ $season->id == $seasonId && request('format', '2v2') == '2v2' ? 'font-bold text-blue-600' : '' }}">2v2</a>
                                    <a href="?season={{ $season->id }}&format=3v3" class="block px-4 w-full text-center py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 {{ $season->id == $seasonId && request('format') == '3v3' ? 'font-bold text-blue-600' : '' }}">3v3</a>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                        <div class="mt-8">
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
                                                <th class="px-2 py-1 text-center text-gray-700">Player</th>
                                                <th class="px-2 py-1 text-center text-gray-700">Race</th>
                                                <th class="px-2 py-1 text-center text-gray-700">APM</th>
                                                <th class="px-2 py-1 text-center text-gray-700">EAPM</th>
                                                <th class="px-2 py-1 text-center text-gray-700">Points</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($team1 as $player)
                                            <tr>
                                                <td class="border text-neonBlue font-semibold border-gray-200 text-center px-2 py-1">
                                                    <a href="{{ route('player', ['user' => $player->player_name]) }}">{{ $player->player_name }}</a>
                                                </td>
                                                <td class="border border-gray-200 text-center px-2 py-1">{{ $player->race }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1">{{ $player->apm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1">{{ $player->eapm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1">{{ $player->points ?? 'N/A' }}</td>
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
                                                <th class="px-2 py-1 text-center text-gray-700">Player</th>
                                                <th class="px-2 py-1 text-center text-gray-700">Race</th>
                                                <th class="px-2 py-1 text-center text-gray-700">APM</th>
                                                <th class="px-2 py-1 text-center text-gray-700">EAPM</th>
                                                <th class="px-2 py-1 text-center text-gray-700">Points</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($team2 as $player)
                                            <tr>
                                                <td class="border text-neonBlue font-semibold border-gray-200 text-center px-2 py-1">
                                                    <a href="{{ route('player', ['user' => $player->player_name]) }}">{{ $player->player_name }}</a>
                                                </td>
                                                <td class="border border-gray-200 text-center px-2 py-1">{{ $player->race }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1">{{ $player->apm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1">{{ $player->eapm ?? 'N/A' }}</td>
                                                <td class="border border-gray-200 text-center px-2 py-1">{{ $player->points ?? 'N/A' }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- Download link below the tables -->
                            <div class="w-full text-center py-2 mb-6">
                                <a href="{{ route('upload.download', ['uuid' => $replayId]) }}" class="inline-block px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition font-semibold">Download Replay</a>
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
