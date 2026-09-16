<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div id="rank-searchbox"></div>
                <div class="w-full">
                    <div class="card">
                <!-- Season Tabs -->
                        <div class="mb-6">
                            <div id="ranking-table"></div>
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

