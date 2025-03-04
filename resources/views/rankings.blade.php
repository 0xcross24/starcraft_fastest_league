<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
      {{ __('Rankings') }}
    </h2>
  </x-slot>

  <div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900 dark:text-gray-100">
          <div class="w-full">
            <div class="card">
              <!-- Season Tabs -->
              <div class="mb-6">
                <ul class="flex border-b border-gray-200">
                  @foreach($seasons as $season)
                  <li class="mr-8">
                    <a href="#season-{{ $season->id }}" class="season-tab inline-block py-2 px-4 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-blue-500 {{ $loop->first ? 'font-bold' : '' }}">
                      Season {{ $season->id }}
                    </a>
                  </li>
                  @endforeach
                </ul>
              </div>

              <!-- Rankings by Season -->
              @foreach($seasons as $season)
              <div id="season-{{ $season->id }}" class="season-ranking {{ $season->id == $activeSeasonId ? 'block' : 'hidden' }}">
                <h3 class="text-lg font-semibold mb-4">Season {{ $season->id }} Ranking</h3>
                <div class="overflow-x-auto">
                  <table class="w-full border-collapse border border-gray-200 mb-4">
                    <thead class="bg-gray-200">
                      <tr>
                        <th class="px-4 py-2 text-left text-center text-gray-700">Player</th>
                        <th class="px-4 py-2 text-left text-center text-gray-700">Rank</th>
                        <th class="px-4 py-2 text-left text-center text-gray-700">Elo</th>
                        <th class="px-4 py-2 text-left text-center text-gray-700">Record</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($usersWithStats[$season->id] ?? collect() as $stat)
                      <tr class="border-b border-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <td class="border border-gray-300 text-center px-4 py-2">
                          @if(isset($stat->user->player_name))
                          <a href="{{ route('player', ['user' => $stat->user->player_name]) }}" class="text-blue-500 hover:underline">
                            {{ $stat->user->player_name }}
                          </a>
                          @else
                          <span class="text-gray-500">Unknown Player</span>
                          @endif
                        </td>
                        <td class="border border-gray-300 text-center px-4 py-2">
                          @switch($stat->elo_grade)
                          @case($stat->elo_grade == 'A')
                          @case($stat->elo_grade == 'A+')
                          @case($stat->elo_grade == 'A-')
                          <span class="text-neonGreen font-bold">{{ $stat->elo_grade ?? 'N/A' }}</span>
                          @break
                          @case($stat->elo_grade == 'B')
                          @case($stat->elo_grade == 'B+')
                          @case($stat->elo_grade == 'B-')
                          <span class="text-neonBlue font-bold">{{ $stat->elo_grade ?? 'N/A' }}</span>
                          @break
                          @case($stat->elo_grade == 'C')
                          @case($stat->elo_grade == 'C+')
                          @case($stat->elo_grade == 'C-')
                          <span class="text-neonYellow font-bold">{{ $stat->elo_grade ?? 'N/A' }}</span>
                          @break
                          @case($stat->elo_grade == 'D')
                          @case($stat->elo_grade == 'D+')
                          @case($stat->elo_grade == 'D-')
                          <span class="text-neonRed font-bold">{{ $stat->elo_grade ?? 'N/A' }}</span>
                          @break
                          @case('E')
                          <span class="text-neonPink font-bold">{{ $stat->elo_grade ?? 'N/A' }}</span>
                          @break
                          @case('S')
                          <span class="text-neonGold font-bold">{{ $stat->elo_grade ?? 'N/A' }}</span>
                          @break
                          @default
                          <span class="text-gray-500 font-bold">{{ $stat->elo_grade ?? 'N/A' }}</span>
                          @endswitch
                        </td>
                        <td class="border border-gray-300 text-center px-4 py-2">{{ $stat->elo ?? 'N/A' }}</td>
                        <td class="border border-gray-300 text-center px-4 py-2">{{ $stat->wins ?? 'N/A' }} - {{ $stat->losses ?? 'N/A' }}</td>
                      </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- JavaScript for Tab Switching -->
  <script>
    document.querySelectorAll('.season-tab').forEach(tab => {
      tab.addEventListener('click', function(e) {
        e.preventDefault();

        // Remove font-bold class from all tabs
        document.querySelectorAll('.season-tab').forEach(t => {
          t.classList.remove('font-bold');
        });

        // Add font-bold class to the clicked tab
        this.classList.add('font-bold');

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
