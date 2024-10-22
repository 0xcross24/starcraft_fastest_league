<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ Auth::user()->player_name }} {{ ($stats->elo ?? 1000) }} {{ ($stats->wins ?? 0) }} - {{ ($stats->losses ?? 0) }}
        </h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="container">
                        <div class="card py-6">
                            <div class="card-header">
                                <h2>Past Match History</h2>
                            </div>
                        </div>
                        <!-- Check if there are any replays -->
                        @if($replays->isEmpty())
                            <p>No replays found for this user.</p>
                        @else
                            @foreach($replays->groupBy('replay_id') as $replayId => $groupedReplays)
                            <table class="min-w-full table-fixed border-collapse border border-gray-200 mb-4">
                                <thead class="bg-gray-200">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-gray-700 w-1/4">Player Name</th>
                                        <th class="px-4 py-2 text-left text-gray-700 w-1/6">Result</th>
                                        <th class="px-4 py-2 text-left text-gray-700 w-1/6">Team</th>
                                        <th class="px-4 py-2 text-left text-gray-700 w-1/4">Replay ID</th>
                                        <th class="px-4 py-2 text-left text-gray-700 w-1/6">APM</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($groupedReplays as $key => $replay)
                                    <tr>
                                        @if($replay->player_name !== $user->player_name)
                                            <td class="border border-gray-300 px-4 py-2">{{ $replay->player_name }}</td>
                                            <td class="border border-gray-300 px-4 py-2">
                                                @if($replay->winning_team == 1)
                                                    <span class="text-emerald-500 font-bold">Win</span>
                                                @else
                                                    <span class="text-red-500 font-bold">Loss</span>
                                                @endif
                                            </td>
                                            <td class="border border-gray-300 px-4 py-2">{{ $replay->team }}</td>
                                            <td class="border border-gray-300 px-4 py-2 w-1/2">
                                                <a href="{{ route('replays.results', ['uuid' => $replay->replay_id]) }}" class="text-blue-600 hover:underline">
                                                    {{ $replay->replay_id }}
                                                </a>
                                            </td>
                                            <td class="border border-gray-300 px-4 py-2">{{ $replay->apm ?? 'N/A' }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            @endforeach
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
