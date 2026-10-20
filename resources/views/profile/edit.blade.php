<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Your Stats</h3>
                    @if($playerName && $playerNames->count() > 1)
                    <x-username-select :names="$playerNames" :selected="$playerName"
                        :url-for="fn($n) => route('profile.edit', ['name' => $n->id, 'season' => $seasonId, 'format' => $format])" />
                    @endif
                </div>
                @if(isset($seasons) && $seasons->count())
                <ul class="flex border-b border-gray-200 mb-2">
                    @foreach($seasons as $season)
                    <li class="mr-8">
                        <a href="?name={{ $playerName?->id }}&season={{ $season->id }}&format={{ $format ?? '2v2' }}"
                            class="season-tab inline-block py-2 px-4 text-sm font-medium focus:outline-none {{ $season->id == $seasonId ? 'border-b-4 border-blue-500 text-gray-900 dark:text-white font-bold dark:bg-gray-900' : 'border-b-0 text-gray-700 dark:text-gray-300 font-normal' }}">
                            Season {{ $season->id }}
                        </a>
                    </li>
                    @endforeach
                </ul>
                <ul class="flex mb-6">
                    <li class="mr-4">
                        <a href="?name={{ $playerName?->id }}&season={{ $seasonId }}&format=2v2"
                            class="inline-block py-1 px-4 text-sm font-medium focus:outline-none {{ ($format ?? request('format', '2v2')) == '2v2' ? 'border-b-4 border-blue-500 text-blue-600 font-bold' : 'border-b-0 text-gray-700 dark:text-gray-300 font-normal' }}">
                            2v2
                        </a>
                    </li>
                    <li>
                        <a href="?name={{ $playerName?->id }}&season={{ $seasonId }}&format=3v3"
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
                            <div class="text-lg font-semibold text-gray-700 dark:text-gray-200">Rank</div>
                            <div class="text-lg font-semibold text-gray-700 dark:text-gray-200 mt-1">#{{ $rank ?? 'N/A' }}</div>
                        </div>
                        <div>
                            <div class="text-lg font-semibold text-gray-700 dark:text-gray-200">Grade</div>
                            <x-elo-grade :elo="$selectedStats->elo ?? 0" class="text-lg font-semibold mt-1 block" />
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
                            <div class="text-lg font-semibold text-gray-700 dark:text-gray-200">ELO</div>
                            <div class="text-lg font-semibold text-gray-700 dark:text-gray-200 mt-1">{{ $selectedStats->elo ?? 'N/A' }}</div>
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
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Your Usernames</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Add the in-game names you play under (up to {{ \App\Models\PlayerName::MAX_PER_USER }}). Replays count for whichever name is in the replay, and each name has its own rank.
                    </p>
                    @if (session('error'))
                    <p class="mt-3 text-sm text-red-500">{{ session('error') }}</p>
                    @endif
                    <ul class="mt-4 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($playerNames as $name)
                        <li class="flex items-center justify-between py-2">
                            <a href="{{ route('player', ['user' => $name->name]) }}" class="text-blue-500 hover:font-bold">{{ $name->name }}</a>
                            @if($name->name === $user->player_name)
                            <span class="text-xs text-gray-500 dark:text-gray-400">Main name</span>
                            @else
                            <form method="post" action="{{ route('player-names.destroy', $name) }}">
                                @csrf
                                @method('delete')
                                <button type="submit" class="text-xs text-red-500 hover:underline">Remove</button>
                            </form>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                    @if($playerNames->count() < \App\Models\PlayerName::MAX_PER_USER)
                    <form method="post" action="{{ route('player-names.store') }}" class="mt-4 flex items-start gap-3">
                        @csrf
                        <div class="flex-1">
                            <x-text-input name="name" type="text" class="block w-full" :value="old('name')" placeholder="In-game name" required maxlength="50" />
                            <x-input-error class="mt-2" :messages="$errors->playerName->get('name')" />
                        </div>
                        <x-primary-button>{{ __('Add') }}</x-primary-button>
                    </form>
                    @endif
                    @if (session('status') === 'username-added')
                    <p class="mt-2 text-sm text-green-600 dark:text-green-400">{{ __('Username added.') }}</p>
                    @elseif (session('status') === 'username-removed')
                    <p class="mt-2 text-sm text-green-600 dark:text-green-400">{{ __('Username removed.') }}</p>
                    @endif
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Download Official SFL Maps</h3>
                <div class="space-y-3">
                    <a href="{{ route('maps.download', ['filename' => 'OP SFL-.scm']) }}"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded shadow focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14m7-7H5" />
                        </svg>
                        Download OP SFL-.scm
                    </a>
                    <a href="{{ route('maps.download', ['filename' => 'SFLClan.scm']) }}"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded shadow focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14m7-7H5" />
                        </svg>
                        Download SFLClan.scm
                    </a>
                    <a href="{{ route('maps.download', ['filename' => 'WkClan  Styler 빠른무한(Ob).scm']) }}"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded shadow focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14m7-7H5" />
                        </svg>
                        Download WkClan Styler 빠른무한(Ob).scm
                    </a>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">Place these maps in your <span class="font-mono">StarCraft/Maps/Download</span> folder before uploading replays.</div>
                </div>
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
