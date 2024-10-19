<x-layout>
  <x-slot:title>
    Results
  </x-slot:title>
  <h1>Replay Details</h1>
  <p>{{ $replays }}</p>
  @foreach($replays as $replay)
    <p>Player: {{ $replay->player_name }}, Winning Team: {{ $replay->winning_team }}</p>
  @endforeach
</x-layout>
