<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $user->player_name }}
        </h2>
        <p class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            @switch($rank)
            @case($rank == 'A')
            @case($rank == 'A+')
            @case($rank == 'A-')
            <span class="text-neonGreen font-bold">{{ $rank ?? 'N/A' }}</span>
            @break
            @case($rank == 'B')
            @case($rank == 'B+')
            @case($rank == 'B-')
            <span class="text-neonBlue font-bold">{{ $rank ?? 'N/A' }}</span>
            @break
            @case($rank == 'C')
            @case($rank == 'C+')
            @case($rank == 'C-')
            <span class="text-neonYellow font-bold">{{ $rank ?? 'N/A' }}</span>
            @break
            @case($rank == 'D')
            @case($rank == 'D+')
            @case($rank == 'D-')
            <span class="text-neonRed font-bold">{{ $rank ?? 'N/A' }}</span>
            @break
            @case('E')
            <span class="text-neonPink font-bold">{{ $rank ?? 'N/A' }}</span>
            @break
            @case('S')
            <span class="text-neonGold font-bold">{{ $rank ?? 'N/A' }}</span>
            @break
            @default
            <span class="text-gray-500 font-bold">{{ $rank ?? 'N/A' }}</span>
            @endswitch
            {{ ($stats->elo ?? 1000) }} {{ ($stats->wins ?? 0) }} - {{ ($stats->losses ?? 0) }}
        </p>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="container w-full">
                        <div class="card">
                            <div class="card-header">
                                <h2>Past Match History</h2>
                            </div>
                            <!-- Season Tabs -->
                            <div class="mb-6">
                                <ul class="flex border-b border-gray-200">
                                    @foreach($seasons as $season)
                                    <li class="mr-8">
                                        <a href="#season-{{ $season->id }}"
                                            class="inline-block py-2 px-4 text-sm text-gray-700 dark:text-gray-300 hover:text-blue-500 
                                           @if($season->id == $activeSeasonId) 
                                               text-blue-600 font-bold 
                                           @endif">
                                            Season {{ $season->id }}
                                        </a>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        <!-- Check if there are any replays -->
                        @if($replays->isEmpty())
                        <p>No replays found for this user.</p>
                        @else
                        <!-- Loop through seasons -->
                        @foreach($seasons as $season)
                        <!-- Create a div for each season, set its id to match the tab -->
                        <div id="season-{{ $season->id }}" class="season-ranking {{ $season->id == $activeSeasonId ? 'block' : 'hidden' }}">
                            @php
                            // Filter replays for this specific season
                            $seasonReplays = $replays->where('season_id', $season->id);
                            @endphp

                            @if($seasonReplays->isEmpty())
                            <p>No replays found for this season.</p>
                            @else
                            @foreach($seasonReplays->groupBy('replay_id') as $replayId => $groupedReplays)
                            <table class="w-full table-fixed border-collapse border border-gray-200 mb-4">
                                <thead class="bg-gray-200">
                                    <tr>
                                        <th class="px-4 py-2 text-center text-gray-700 w-1/4">Player Name</th>
                                        <th class="px-4 py-2 text-center text-gray-700 w-1/6">Result</th>
                                        <th class="px-4 py-2 text-center text-gray-700 w-1/6">Team</th>
                                        <th class="px-4 py-2 text-center text-gray-700 w-1/4">Race</th>
                                        <th class="px-4 py-2 text-center text-gray-700 w-1/6">APM</th>
                                        <th class="px-4 py-2 text-center text-gray-700 w-1/6">EAPM</th>
                                        <th class="px-4 py-2 text-center text-gray-700 w-1/6">Points</th>
                                        <th class="px-4 py-2 text-center text-gray-700 w-1/6">New ELO</th>
                                        <th class="px-4 py-2 text-center text-gray-700 w-1/4">Replays</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($groupedReplays as $replay)
                                    <tr>
                                        <td class="border text-blue-600 border-gray-300 text-center px-4 py-2"><a href="{{ route('player', ['user' => $replay->player_name]) }}">{{ $replay->player_name }}</a></td>
                                        <td class="border border-gray-300 text-center px-4 py-2">
                                            @if($replay->winning_team == 1)
                                            <span class="text-emerald-500 font-bold">Win</span>
                                            @else
                                            <span class="text-red-500 font-bold">Loss</span>
                                            @endif
                                        </td>
                                        <td class="border border-gray-300 text-center px-4 py-2">{{ $replay->team }}</td>
                                        <td class="border border-gray-300 text-center px-4 py-2">{{ $replay->race }}</td>
                                        <td class="border border-gray-300 text-center px-4 py-2">{{ $replay->apm ?? 'N/A' }}</td>
                                        <td class="border border-gray-300 text-center px-4 py-2">{{ $replay->eapm ?? 'N/A' }}</td>
                                        <td class="border border-gray-300 text-center px-4 py-2">{{ $replay->points ?? 'N/A' }}</td>
                                        <td class="border border-gray-300 text-center px-4 py-2">
                                            {{ $userStats[$replay->user_id]->elo ?? 'N/A' }} <!-- Accessing user stats -->
                                        </td>
                                        <td class="border border-gray-300 text-center px-4 py-2 w-1/3">
                                            <a href="{{ route('replays.download', ['uuid' => $replay->replay_id]) }}" class="text-blue-600 hover:underline">
                                                Download
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @endforeach
                            @endif
                        </div>
                        @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add JavaScript to toggle between season tabs -->
    <script>
        document.querySelectorAll('a[href^="#season-"]').forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();

                // Hide all season rankings
                document.querySelectorAll('.season-ranking').forEach(section => {
                    section.classList.add('hidden');
                });

                // Show the clicked season's ranking
                const seasonId = this.getAttribute('href').replace('#', '');
                document.getElementById(seasonId).classList.remove('hidden');
            });
        });
    </script>
</x-app-layout>
