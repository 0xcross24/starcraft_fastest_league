<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                @include('admin.partials.nav')

                <form method="get" class="flex flex-wrap gap-2 mb-4">
                    <x-text-input name="player" type="search" :value="$player" placeholder="Player name" class="flex-1 min-w-0" />
                    <select name="season" class="rounded-md border-gray-700 bg-gray-900 text-gray-200 text-sm">
                        <option value="">All seasons</option>
                        @foreach($seasons as $season)
                        <option value="{{ $season->id }}" @selected($seasonId == $season->id)>Season {{ $season->id }}</option>
                        @endforeach
                    </select>
                    <select name="format" class="rounded-md border-gray-700 bg-gray-900 text-gray-200 text-sm">
                        <option value="">All formats</option>
                        @foreach(['2v2', '3v3'] as $f)
                        <option value="{{ $f }}" @selected($format === $f)>{{ $f }}</option>
                        @endforeach
                    </select>
                    <x-primary-button>Filter</x-primary-button>
                </form>

                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <p class="text-sm text-gray-400">{{ $games->total() }} {{ Str::plural('game', $games->total()) }}</p>
                    <div class="flex flex-wrap items-center gap-2">
                        {{-- Whole-season backup: valid games of every format, unless a format is picked --}}
                        @if($seasonId)
                        <x-zip-download-button :season="$seasonId" :format="$format" :label="$format ? 'Download ' . $format : 'Download whole season'" />
                        <x-zip-date-download :season="$seasonId" :format="$format" />
                        @endif
                        <button type="button" id="select-page" class="text-xs text-blue-400 hover:underline">Select all on page</button>
                        {{-- Ticked games, any season; invalid games have no box and are never zipped --}}
                        <x-zip-selected-download :season="$seasonId" :format="$format" />
                    </div>
                </div>

                <div class="space-y-4">
                    @forelse($games as $game)
                    @php
                    $players = $rows[$game->replay_id] ?? collect();
                    $first = $players->first();
                    $invalid = $players->isNotEmpty() && $players->every(fn($r) => $r->is_invalid);
                    @endphp
                    <div class="rounded border {{ $invalid ? 'border-red-800 opacity-60' : 'border-gray-700' }} p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-400">
                            <div>
                                <span class="font-mono text-gray-300">{{ substr($game->replay_id, 0, 8) }}</span>
                                · Season {{ $first?->season_id }} · {{ $first?->format }}
                                · uploaded {{ \Carbon\Carbon::parse($game->uploaded_at)->format('Y-m-d H:i') }}
                                @if($invalid)<span class="ml-1 rounded bg-red-900 px-2 py-0.5 text-red-200">invalid</span>@endif
                            </div>
                            <div class="flex items-center gap-3">
                                @unless($invalid)
                                <label class="js-zip-pick-label items-center gap-1 cursor-pointer select-none" style="display: none">
                                    <input type="checkbox" class="js-zip-pick rounded border-gray-400 bg-gray-900 text-blue-600" value="{{ $game->replay_id }}">
                                    Select
                                </label>
                                @endunless
                                <a href="{{ route('replay.download', ['uuid' => $game->replay_id]) }}" class="text-blue-400 hover:underline">Download</a>
                                @unless($invalid)
                                <form method="post" action="{{ route('admin.replays.invalidate', $game->replay_id) }}"
                                    onsubmit="return confirm(@js('Invalidate game '.substr($game->replay_id, 0, 8).'? This reverses the ELO and wins/losses it gave every player.'))">
                                    @csrf
                                    <button class="text-red-400 hover:underline">Invalidate</button>
                                </form>
                                @endunless
                            </div>
                        </div>
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                            @foreach($players->groupBy('team') as $team => $teamRows)
                            <div>
                                <div class="font-semibold {{ $teamRows->first()->winning_team ? 'text-emerald-400' : 'text-red-400' }}">
                                    Team {{ $team }} {{ $teamRows->first()->winning_team ? 'won' : 'lost' }}
                                </div>
                                @foreach($teamRows as $row)
                                <div class="flex justify-between gap-2">
                                    <span class="truncate">{{ $row->player_name }} <span class="text-gray-400">{{ $row->race }}</span></span>
                                    <span class="{{ $row->points >= 0 ? 'text-emerald-400' : 'text-red-400' }}">{{ $row->points >= 0 ? '+' : '' }}{{ $row->points }}</span>
                                </div>
                                @endforeach
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @empty
                    <p class="text-gray-400">No games match.</p>
                    @endforelse
                </div>

                <div class="mt-4">{{ $games->links() }}</div>
                <script>
                document.getElementById('select-page').addEventListener('click', () => {
                    // Stops at the pick limit (the change handler unticks and warns once)
                    for (const c of document.querySelectorAll('.js-zip-pick:not(:checked)')) {
                        c.checked = true;
                        c.dispatchEvent(new Event('change'));
                        if (!c.checked) break;
                    }
                });
                </script>
            </div>
        </div>
    </div>
</x-app-layout>
