<x-app-layout>
  <div class="py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900 dark:text-gray-100">
          <h2 class="text-xl mb-4">
            @if($search === '')
            Search players
            @else
            Players matching "{{ $search }}"
            @endif
          </h2>

          <x-player-search class="mb-6 sm:hidden" />

          @if($search !== '' && $names->isEmpty())
          <p class="text-gray-400">No players found.</p>
          @else
          <ul class="divide-y divide-gray-700">
            @foreach($names as $name)
            <li><a href="{{ route('player', $name) }}" class="block py-2 text-blue-400 hover:underline">{{ $name }}</a></li>
            @endforeach
          </ul>
          @endif
        </div>
      </div>
    </div>
  </div>
</x-app-layout>
