<x-layout>
  <x-slot:title>
    Rankings
  </x-slot:title>
  <h1>Rankings</h1>
  
  <table>
  <tr>
    <th>Rank</th>
    <th>League</th>
    <th>Player</th>
    <th>Points</th>
    <th>Record</th>
    <th>Country</th>
  </tr>
  @foreach($rankings as $ranking)
    <tr>
      <td>{{$ranking['rank']}}</td>
      <td>{{$ranking['league']}}</td>
      <td>{{$ranking['player']}}</td>
      <td>{{$ranking['points']}}</td>
      <td>{{$ranking['record']}}</td>
      <td>{{$ranking['country']}}</td>
    </tr>  
  @endforeach
  </table>
</x-layout>