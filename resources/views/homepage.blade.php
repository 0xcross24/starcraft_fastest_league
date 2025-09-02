<x-guest-layout>
  <div class="pb-12">
    <div class="grid grid-cols-2 md:grid-cols-2 gap-4 mt-6">
      <!-- 2v2 Leaderboard -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-3 w-full">
        <h2 class="text-lg font-bold mb-2 text-center text-gray-700 dark:text-gray-200 font-logo">Current Season 2v2 Top 5</h2>
        @if($season)
        <table class="w-full border-collapse border border-gray-200 text-sm mb-2">
          <thead class="bg-gray-200 dark:bg-gray-700 font-nav">
            <tr>
              <th class="px-2 py-1 text-center text-gray-700 dark:text-gray-200 font-nav">#</th>
              <th class="px-2 py-1 text-center text-gray-700 dark:text-gray-200 font-nav">Player</th>
              <th class="px-2 py-1 text-center text-gray-700 dark:text-gray-200 font-nav">ELO</th>
              <th class="px-2 py-1 text-center text-gray-700 dark:text-gray-200 font-nav">Grade</th>
              <th class="px-2 py-1 text-center text-gray-700 dark:text-gray-200 font-nav">Record</th>
            </tr>
          </thead>
          <tbody class="text-gray-900 dark:text-gray-100">
            @php $eloService = app('App\\Services\\EloService'); $rankNum = 1; @endphp
            @foreach($top2v2 as $stat)
            <tr class="border-b border-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
              <td class="border border-gray-300 text-center px-2 py-1">{{ $rankNum++ }}</td>
              <td class="border border-gray-300 text-center px-2 py-1">
                @if(isset($stat->user->player_name))
                <a href="{{ route('player', ['user' => $stat->user->player_name]) }}" class="text-blue-500 font-semibold hover:font-bold">
                  {{ $stat->user->player_name }}
                </a>
                @else
                <span class="text-gray-500">Unknown Player</span>
                @endif
              </td>
              <td class="border border-gray-300 text-center px-2 py-1">{{ $stat->elo ?? 'N/A' }}</td>
              @php $grade = $eloService->getEloGrade($stat->elo ?? 0); $color = $eloService->getGradeColorClass($grade); @endphp
              <td class="border border-gray-300 text-center px-2 py-1"><span class="font-bold {{ $grade === 'S' ? 'text-neonGold' : $color }}">{{ $grade }}</span></td>
              <td class="border border-gray-300 text-center px-2 py-1">{{ $stat->wins ?? 0 }} - {{ $stat->losses ?? 0 }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
        {{-- Button to view full 2v2 standings --}}
        <div class="mt-2 text-center" style="width: 100%;">
          <a href="{{ route('rankings', ['format' => '2v2', 'season_id' => $season->id]) }}" class="inline-block px-3 py-1 bg-blue-600 text-white text-center text-xs rounded hover:bg-blue-700 transition font-semibold font-nav">
            View 2v2 Standings
          </a>
        </div>
        @else
        <div class="text-center text-lg text-red-500 font-bold my-8">No active season</div>
        @endif
      </div>
      <!-- 3v3 Leaderboard -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-3 w-full">
        <h2 class="text-lg font-bold mb-2 text-center text-gray-700 dark:text-gray-200 font-logo">Current Season 3v3 Top 5</h2>
        @if($season)
        <table class="w-full border-collapse border border-gray-200 text-sm mb-2">
          <thead class="bg-gray-200 dark:bg-gray-700 font-nav">
            <tr>
              <th class="px-2 py-1 text-center text-gray-700 dark:text-gray-200 font-nav">#</th>
              <th class="px-2 py-1 text-center text-gray-700 dark:text-gray-200 font-nav">Player</th>
              <th class="px-2 py-1 text-center text-gray-700 dark:text-gray-200 font-nav">ELO</th>
              <th class="px-2 py-1 text-center text-gray-700 dark:text-gray-200 font-nav">Grade</th>
              <th class="px-2 py-1 text-center text-gray-700 dark:text-gray-200 font-nav">Record</th>
            </tr>
          </thead>
          <tbody class="text-gray-900 dark:text-gray-100">
            @php $eloService = app('App\\Services\\EloService'); $rankNum = 1; @endphp
            @foreach($top3v3 as $stat)
            <tr class="border-b border-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
              <td class="border border-gray-300 text-center px-2 py-1">{{ $rankNum++ }}</td>
              <td class="border border-gray-300 text-center font-semibold px-2 py-1">
                @if(isset($stat->user->player_name))
                <a href="{{ route('player', ['user' => $stat->user->player_name]) }}" class="text-blue-500 hover:font-bold">
                  {{ $stat->user->player_name }}
                </a>
                @else
                <span class="text-gray-500">Unknown Player</span>
                @endif
              </td>
              <td class="border border-gray-300 text-center px-2 py-1">{{ $stat->elo ?? 'N/A' }}</td>
              @php $grade = $eloService->getEloGrade($stat->elo ?? 0); $color = $eloService->getGradeColorClass($grade); @endphp
              <td class="border border-gray-300 text-center px-2 py-1"><span class="font-bold {{ $grade === 'S' ? 'text-neonGold' : $color }}">{{ $grade }}</span></td>
              <td class="border border-gray-300 text-center px-2 py-1">{{ $stat->wins ?? 0 }} - {{ $stat->losses ?? 0 }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
        {{-- Button to view full 3v3 standings --}}
        <div class="mt-2 text-center" style="width: 100%;">
          <a href="{{ route('rankings', ['format' => '3v3', 'season_id' => $season->id]) }}" class="inline-block px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition font-semibold font-nav">
            View 3v3 Standings
          </a>
        </div>
        @else
        <div class="text-center text-lg text-red-500 font-bold my-8">No active season</div>
        @endif
      </div>
    </div>

    <!-- Last 5 Replays Full Details -->
    <h2 class="text-lg font-bold mt-7 text-center text-gray-700 dark:text-gray-200 font-logo">Recently Played Matches</h2>
    <div class="grid grid-cols-3 gap-1 mt-5">
      @forelse($recentReplayGroups as $group)
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-3 w-full">
        <div class="w-full text-center text-xs text-gray-300 mb-6">
          <div class="w-full text-xs text-gray-300 px-3 py-2 border-b border-gray-200 dark:border-gray-700">
            Replay ID: <span class="font-mono">{{ substr($group->first()->replay_id, 0, 8) }}</span>
          </div>
          <div class="w-full text-xs text-gray-300 px-3 pt-2">
            Uploaded {{ $group->first()->created_at ? $group->first()->created_at->diffForHumans() : '' }}
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full border-collapse border border-gray-200 text-sm mb-2">
            <thead class="bg-gray-200 dark:bg-gray-700 font-nav">
              <tr>
                <th class="px-2 py-1 text-center text-gray-700 dark:text-gray-200 font-nav">Player</th>
                <th class="px-2 py-1 text-center text-gray-700 dark:text-gray-200 font-nav">Result</th>
                <th class="px-2 py-1 text-center text-gray-700 dark:text-gray-200 font-nav">Points</th>
                <th class="px-2 py-1 text-center text-gray-700 dark:text-gray-200 font-nav">APM</th>
              </tr>
            </thead>
            <tbody class="text-gray-900 dark:text-gray-100">
              @foreach($group as $replay)
              <tr class="border-b border-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                <td class="border border-gray-300 text-center px-2 py-1 font-semibold text-blue-500">
                  @if($replay->player_name)
                  <a href="{{ route('player', ['user' => $replay->player_name]) }}" class="hover:font-bold">{{ $replay->player_name }}</a>
                  @else
                  Unknown Player
                  @endif
                </td>
                <td class="border border-gray-300 text-center px-2 py-1">
                  @if($replay->winning_team == 1)
                  <span class="text-green-600 font-bold">Win</span>
                  @else
                  <span class="text-red-500 font-bold">Lost</span>
                  @endif
                </td>
                <td class="border border-gray-300 text-center px-2 py-1">{{ $replay->points }}</td>
                <td class="border border-gray-300 text-center px-2 py-1">{{ $replay->apm }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <!-- Download link below the tables -->
        <div class="w-full text-center py-2 mb-1">
          <a href="{{ route('replay.download', ['uuid' => $group->first()->replay_id]) }}" class="inline-block px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition font-semibold font-nav">Download Replay</a>
        </div>
      </div>
      @empty
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-3 w-full text-center text-gray-500">
        No replay data found.
      </div>
      @endforelse
    </div>
  </div>
</x-guest-layout>
