<div class="overflow-x-auto">
  <table class="w-full border-collapse border border-gray-200 mb-4">
    <thead class="bg-gray-200 dark:bg-gray-700">
      <tr>
        <th class="px-4 py-2 text-center text-gray-700 dark:text-gray-200">#</th>
        <th class="px-4 py-2 text-center text-gray-700 dark:text-gray-200">Player</th>
        <th class="px-4 py-2 text-center text-gray-700 dark:text-gray-200">Rank</th>
        <th class="px-4 py-2 text-center text-gray-700 dark:text-gray-200">Elo</th>
        <th class="px-4 py-2 text-center text-gray-700 dark:text-gray-200">Record</th>
      </tr>
    </thead>
    <tbody id="ranking-body">
      @php $rankNum = 1; @endphp
      @forelse($stats as $stat)
        <tr class="border-b border-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
          <td class="border border-gray-300 text-center px-4 py-2">{{ $rankNum++ }}</td>
          <td class="border border-gray-300 text-center px-4 py-2">
            @if(isset($stat->user->player_name))
              <a href="{{ route('player', ['user' => $stat->user->player_name]) }}"
                 class="font-semibold text-blue-500 hover:font-bold">
                {{ $stat->user->player_name }}
              </a>
            @else
              <span class="text-gray-500">Unknown Player</span>
            @endif
          </td>
          <td class="border border-gray-300 text-center px-4 py-2">
            @php
              $eloService = app('App\\Services\\EloService');
              $elo = $stat->elo ?? 0;
              $grade = $eloService->getEloGrade($elo);
              $color = $eloService->getGradeColorClass($grade);
            @endphp
            <span class="font-bold {{ $grade === 'S' ? 'text-neonGold' : $color }}">
              {{ $grade }}
            </span>
          </td>
          <td class="border border-gray-300 text-center px-4 py-2">{{ $stat->elo ?? 'N/A' }}</td>
          <td class="border border-gray-300 text-center px-4 py-2">
            {{ $stat->wins ?? 'N/A' }} - {{ $stat->losses ?? 'N/A' }}
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="text-center py-4 text-gray-500 dark:text-gray-400">
            No results found.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
