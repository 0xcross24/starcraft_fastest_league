<x-app-layout>
  <x-slot:title>
    Results
  </x-slot:title>
  <h1>Replay Details</h1>
  @if($replays->isEmpty())
    <p>No replays found for this ID.</p>
  @else
  
  <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="container">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <h2 class="text-lg font-bold mb-4">Winners</h2>
                                <ul>
                                    @foreach($replays as $replay)
                                        @if($replay->winning_team == 1)
                                            <li class="p-4 border-b">
                                                <strong>Player Name:</strong> {{ $replay->player_name }}<br>
                                                <strong>Start Time:</strong> {{ $replay->start_time }}<br>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                            
                            <div>
                                <h2 class="text-lg font-bold mb-4">Losers</h2>
                                <ul>
                                    @foreach($replays as $replay)
                                        @if($replay->winning_team == 0)
                                            <li class="p-4 border-b">
                                                <strong>Player Name:</strong> {{ $replay->player_name }}<br>
                                                <strong>Start Time:</strong> {{ $replay->start_time }}<br>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
