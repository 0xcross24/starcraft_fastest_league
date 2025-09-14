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
            @php
              $format = request('format', '2v2');
              $selectedSeasonId = request('season') ?? ($seasons->count() ? $seasons->max('id') : null);
            @endphp

            <form id="search-form" class="mb-4 flex gap-4">
              <input type="text" name="search" id="search-input"
                placeholder="Search player..."
                value="{{ request('search') }}"
                class="px-4 py-2 rounded border border-gray-300 dark:bg-gray-700 dark:text-white" />

              <input type="hidden" name="format" value="{{ request('format', '2v2') }}">

              <button type="submit"
                      class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                  Search
              </button>
            </form>

            <div class="w-full">
              <div class="card">
                <!-- Season Tabs -->
                <div class="mb-6">
                  <ul class="flex border-b border-gray-200 mb-2">
                    @foreach($seasons as $season)
                      @if($season->id == $selectedSeasonId)
                        <div id="season-{{ $season->id }}" class="season-ranking block">
                          <h3 class="text-lg font-semibold mb-4">
                            Season {{ $season->id }} Ranking ({{ strtoupper($format) }})
                          </h3>

                          <div class="overflow-x-auto" id="ranking-table">
                            @include('partials.ranking-table', [
                                'stats' => $usersWithStats[$season->id] ?? collect(),
                                'format' => $format
                            ])
                          </div>
                        </div>
                      @endif
                    @endforeach
                  </ul>

                  <!-- Format Tabs -->
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
              </div>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

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

<script>
  document.getElementById('search-form').addEventListener('submit', function(e) {
    e.preventDefault();

    let search = document.getElementById('search-input').value;
    let format = document.querySelector('input[name="format"]').value;

    fetch(`/rankings/search?search=${encodeURIComponent(search)}&format=${format}`)
      .then(res => res.json())
      .then(data => {
        if (data.html) {
          document.getElementById('ranking-table').innerHTML = data.html;
        }
      });
  });
</script>

