<div class="mb-6">
    <ul class="flex border-b border-gray-200">
        @foreach($seasons as $season)
        <li class="mr-8 relative season-dropdown">
            <button class="season-tab inline-block py-2 px-4 text-sm font-medium border-b-4 focus:outline-none border-transparent text-gray-700 dark:text-gray-300">
                Season {{ $season->id }}
            </button>
            <div class="dropdown-menu absolute px-4 left-0 mt-2 min-w-full bg-white dark:bg-gray-800 border border-gray-200 rounded shadow-lg z-50 hidden" style="pointer-events: auto;">
                <a href="?season={{ $season->id }}&format=2v2" class="block px-4 w-full text-center py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">2v2</a>
                <a href="?season={{ $season->id }}&format=3v3" class="block px-4 w-full text-center py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">3v3</a>
            </div>
        </li>
        @endforeach
    </ul>
    <script src="/js/season-dropdown.js"></script>
</div>
