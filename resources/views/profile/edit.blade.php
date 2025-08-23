<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Your Stats</h3>
                @if(isset($seasons) && $seasons->count())
                <ul class="flex border-b border-gray-200 mb-2">
                    @foreach($seasons as $season)
                    <li class="mr-8">
                        <a href="?season={{ $season->id }}&format={{ $format ?? '2v2' }}"
                            class="season-tab inline-block py-2 px-4 text-sm font-medium focus:outline-none {{ $season->id == $seasonId ? 'border-b-4 border-blue-500 text-white font-bold dark:bg-gray-900' : 'border-b-0 text-gray-700 dark:text-gray-300 font-normal' }}">
                            Season {{ $season->id }}
                        </a>
                    </li>
                    @endforeach
                </ul>
                <ul class="flex mb-6">
                    <li class="mr-4">
                        <a href="?season={{ $seasonId }}&format=2v2"
                            class="inline-block py-1 px-4 text-sm font-medium focus:outline-none {{ ($format ?? request('format', '2v2')) == '2v2' ? 'border-b-4 border-blue-500 text-blue-600 font-bold' : 'border-b-0 text-gray-700 dark:text-gray-300 font-normal' }}">
                            2v2
                        </a>
                    </li>
                    <li>
                        <a href="?season={{ $seasonId }}&format=3v3"
                            class="inline-block py-1 px-4 text-sm font-medium focus:outline-none {{ ($format ?? request('format')) == '3v3' ? 'border-b-4 border-blue-500 text-blue-600 font-bold' : 'border-b-0 text-gray-700 dark:text-gray-300 font-normal' }}">
                            3v3
                        </a>
                    </li>
                </ul>
                @php
                $selectedStats = $stats;
                @endphp
                @if($selectedStats)
                <div class="mt-6">
                    <div class="flex flex-wrap items-center gap-8 bg-gray-100 dark:bg-gray-700 rounded-lg p-6 mb-6">
                        <div>
                            <div class="text-lg font-semibold text-gray-700 dark:text-gray-200">Season</div>
                            <div class="text-lg font-semibold mt-1 text-white">{{ $seasonId }}</div>
                        </div>
                        <div>
                            <div class="text-lg font-semibold text-gray-700 dark:text-gray-200">Format</div>
                            <div class="text-lg font-semibold mt-1 text-white">{{ strtoupper($format) }}</div>
                        </div>
                        <div>
                            <div class="text-lg font-semibold text-gray-700 dark:text-gray-200">ELO</div>
                            <div class="text-lg font-semibold text-white mt-1">{{ $selectedStats->elo ?? 'N/A' }}</div>
                        </div>
                        <div>
                            <div class="text-lg font-semibold text-gray-700 dark:text-gray-200">Wins</div>
                            <div class="text-lg font-semibold text-green-400 dark:text-green-400 mt-1">{{ $selectedStats->wins ?? 0 }}</div>
                        </div>
                        <div>
                            <div class="text-lg font-semibold text-gray-700 dark:text-gray-200">Losses</div>
                            <div class="text-lg font-semibold text-red-500 dark:text-red-500 mt-1">{{ $selectedStats->losses ?? 0 }}</div>
                        </div>
                        <div>
                            <div class="text-lg font-semibold text-gray-700 dark:text-gray-200">Rank</div>
                            <div class="text-lg font-semibold text-blue-600 dark:text-blue-400 mt-1">{{ $rank ?? 'N/A' }}</div>
                        </div>
                    </div>
                </div>
                @else
                <div class="mt-6 text-gray-500 dark:text-gray-400">No stats for this season/format.</div>
                @endif
                @else
                <p class="text-gray-600 dark:text-gray-300">No stats available.</p>
                @endif
            </div>
            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
