<x-app-layout>
  <x-slot:title>
    Results
  </x-slot:title>
  <h1>Replay Details</h1>
  @if($replays->isEmpty())
    <p>No replays found for this ID.</p>
  @else
  
  
  <div class="grid grid-cols-2 gap-4">
    <div>
        <h2 class="text-lg font-bold mb-4">Winners</h2>
        <ul>
            @foreach($replays as $replay)
                @if($replay->winning_team == 1)
                    <li class="p-4 border-b">
                        <strong>Player Name:</strong> {{ $replay->player_name }}<br>
                        <strong>Start Time:</strong> {{ $replay->start_time }}<br>
                        <strong>Replay File:</strong> <a href="{{ asset($replay->replay_file) }}">{{ basename($replay->replay_file) }}</a>
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
                        <strong>Replay File:</strong> <a href="{{ asset($replay->replay_file) }}">{{ basename($replay->replay_file) }}</a>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
</div>
@endif

</x-app-layout>
