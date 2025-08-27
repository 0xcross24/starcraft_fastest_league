<x-app-layout>
  <div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900 dark:text-gray-100">
          @if(isset($noSeasonMessage))
          <div class="text-center text-lg text-red-500 font-bold my-8">
            {{ $noSeasonMessage }}
          </div>
          @else
          <div class="w-full">
            <div class="card">
              <!-- Season Tabs -->
              @php
              $selectedSeasonId = request('season') ?? ($seasons->count() ? $seasons->max('id') : null);
              @endphp
              <div class="mb-6">
                <ul class="flex border-b border-gray-200 mb-2">
                  @foreach($seasons as $season)
                  <li class="mr-8">
                    <a href="?season={{ $season->id }}&format={{ request('format', '2v2') }}"
                      class="season-tab inline-block py-2 px-4 text-sm font-medium focus:outline-none {{ $season->id == $selectedSeasonId ? 'border-b-4 border-blue-500 text-white font-bold dark:bg-gray-900' : 'border-b-0 text-gray-700 dark:text-gray-300' }}">
                      Season {{ $season->id }}
                    </a>
                  </li>
                  @endforeach
                </ul>
                <ul class="flex mb-6">
                  <li class="mr-4">
                    <a href="?season={{ $selectedSeasonId }}&format=2v2"
                      class="inline-block py-1 px-4 text-sm font-medium focus:outline-none {{ request('format', '2v2') == '2v2' ? 'border-b-4 border-blue-500 text-blue-600 font-bold' : 'border-b-0 text-gray-700 dark:text-gray-300 font-bold' }}">
                      2v2
                    </a>
                  </li>
                  <li>
                    <a href="?season={{ $selectedSeasonId }}&format=3v3"
                      class="inline-block py-1 px-4 text-sm font-medium focus:outline-none {{ request('format') == '3v3' ? 'border-b-4 border-blue-500 text-blue-600 font-bold' : 'border-b-0 text-gray-700 dark:text-gray-300 font-bold' }}">
                      3v3
                    </a>
                  </li>
                </ul>
              </div>

              <!-- Rankings by Season with 2v2/3v3 Tabs -->
              @php
              $format = request('format', '2v2');
              @endphp

              @foreach($seasons as $season)
              @if($season->id == $selectedSeasonId)
              <div id="season-{{ $season->id }}" class="season-ranking block">
                <h3 class="text-lg font-semibold mb-4">Season {{ $season->id }} Ranking ({{ strtoupper($format) }})</h3>
                <div class="overflow-x-auto">
                  <table class="w-full border-collapse border border-gray-200 mb-4">
                    <thead class="bg-gray-200 dark:bg-gray-700">
                      <tr>
                        <th class="px-4 py-2 text-left text-center text-gray-200">#</th>
                        <th class="px-4 py-2 text-left text-center text-gray-200">Player</th>
                        <th class="px-4 py-2 text-left text-center text-gray-200">Rank</th>
                        <th class="px-4 py-2 text-left text-center text-gray-200">Elo</th>
                        <th class="px-4 py-2 text-left text-center text-gray-200">Record</th>
                      </tr>
                    </thead>
                    <tbody>
                      @php $rankNum = 1; @endphp
                      @foreach(($usersWithStats[$season->id] ?? collect())->where('format', $format) as $stat)
                      <tr class="border-b border-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <td class="border border-gray-300 text-center px-4 py-2">{{ $rankNum++ }}</td>
                        <td class="border border-gray-300 text-center px-4 py-2">
                          @if(isset($stat->user->player_name))
                          <a href="{{ route('player', ['user' => $stat->user->player_name]) }}" class="font-semibold text-blue-500 hover:font-bold">
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
                          <span class="font-bold {{ $grade === 'S' ? 'text-neonGold' : $color }}">{{ $grade }}</span>
                        </td>
                        <td class="border border-gray-300 text-center px-4 py-2">{{ $stat->elo ?? 'N/A' }}</td>
                        <td class="border border-gray-300 text-center px-4 py-2">{{ $stat->wins ?? 'N/A' }} - {{ $stat->losses ?? 'N/A' }}</td>
                      </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
              @endif
              @endforeach
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  @endif

  <!-- Use the same dropdown JS as player.blade.php -->
  <script src="/js/season-dropdown.js"></script>
  <!-- Neon Gold CSS for S grade -->
  <style>
    .text-neonGold {
      color: #FFD700;
      text-shadow: 0 0 8px #FFD700, 0 0 16px #FFD700;
    }
  </style>
</x-app-layout>
