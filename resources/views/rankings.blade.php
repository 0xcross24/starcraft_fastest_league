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
              <div class="card-header pb-4">
                <h2 class="text-xl font-bold">Current Standing</h2>
              </div>

              <!-- Season Tabs -->
              <div class="mb-6">
                <ul class="flex border-b border-gray-200">
                  @foreach($seasons as $season)
                  <li class="mr-8">
                    <a href="#season-{{ $season->id }}" class="inline-block py-2 px-4 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-blue-500">
                      Season {{ $season->id }}
                    </a>
                  </li>
                  @endforeach
                </ul>
              </div>

              <!-- Rankings by Season -->
              @foreach($seasons as $season)
              <div id="season-{{ $season->id }}" class="season-ranking {{ $loop->first ? 'block' : 'hidden' }}">
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
                      @foreach($usersWithStats as $user)
                       <!-- Ensure stats belong to the current season -->
                      <tr class="border-b border-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <td class="border border-gray-300 text-center px-4 py-2">
                          <a href="{{ route('player', ['user' => $user->player_name]) }}" class="text-blue-500 hover:underline">
                            {{ $user->player_name }}
                          </a>
                        </td>
                        <td class="border border-gray-300 text-center px-4 py-2">{{ $user->elo_grade }}</td>
                        <td class="border border-gray-300 text-center px-4 py-2">{{ $user->stats->elo ?? 'N/A' }}</td>
                        <td class="border border-gray-300 text-center px-4 py-2">{{ $user->stats->wins ?? 'N/A' }} - {{ $user->stats->losses ?? 'N/A' }}</td>
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

  <!-- Add a simple JavaScript to toggle between season tabs -->
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
