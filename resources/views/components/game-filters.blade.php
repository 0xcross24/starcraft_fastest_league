@props(['filter', 'count', 'season' => null, 'format' => null, 'keep' => [], 'withPlayer' => true])

{{-- Date / player / race filters for a game list (App\Support\GameFilter), with a
     line saying what is shown. $keep = other query values to carry (season, format, result). --}}
@php
$open = $filter->isActive() || $errors->any();
$clearUrl = '?' . http_build_query(array_filter($keep)); // relative: same page, filters dropped
$chip = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200';
@endphp
<div class="mb-4 text-sm">
    <div class="flex flex-wrap items-center gap-2 text-gray-600 dark:text-gray-300">
        <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $count }} {{ Str::plural('game', $count) }}</span>
        @if($season)<span>· Season {{ $season }}</span>@endif
        @if($format)<span>· {{ strtoupper($format) }}</span>@endif
        @if($filter->dateLabel())<span class="{{ $chip }}">{{ $filter->dateLabel() }}</span>@endif
        @if($filter->player)
        <span class="{{ $chip }}">Player: {{ $filter->player }}{{ $filter->race ? ' as ' . $filter->race : '' }}@unless($filter->playerName) <em>(not found)</em>@endunless</span>
        @elseif($filter->race)
        <span class="{{ $chip }}">Race: {{ $filter->race }}</span>
        @endif
        @if($filter->isActive())
        <a href="{{ $clearUrl }}" class="text-xs text-gray-500 dark:text-gray-400 hover:underline">✕ Clear filters</a>
        @endif
    </div>

    {{-- Always open on desktop (no toggle); a collapsible "Filters" on phones --}}
    <details class="mt-2 game-filters" @if($open) open @endif>
        <summary class="md:hidden cursor-pointer select-none text-xs font-semibold text-blue-600 dark:text-blue-400">Filters</summary>
        <form method="GET" class="mt-2 flex flex-wrap items-end gap-3 p-3 rounded-lg bg-gray-100 dark:bg-gray-700/50">
            @foreach(array_filter($keep) as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <label class="flex flex-col gap-1 text-xs text-gray-600 dark:text-gray-300">
                From
                <input type="date" name="from" value="{{ $filter->from }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-xs py-1">
            </label>
            <label class="flex flex-col gap-1 text-xs text-gray-600 dark:text-gray-300">
                To
                <input type="date" name="to" value="{{ $filter->to }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-xs py-1">
            </label>
            @if($withPlayer)
            <label class="flex flex-col gap-1 text-xs text-gray-600 dark:text-gray-300">
                Player
                <input type="text" name="player" value="{{ $filter->player }}" placeholder="Exact name" maxlength="255" class="w-40 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-xs py-1">
            </label>
            @endif
            <label class="flex flex-col gap-1 text-xs text-gray-600 dark:text-gray-300">
                Race
                <select name="race" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-xs py-1 pr-8">
                    <option value="">Any</option>
                    @foreach(\App\Support\GameFilter::RACES as $race)
                    <option value="{{ $race }}" @selected($filter->race === $race)>{{ $race }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition font-semibold">Apply</button>
            @if($filter->isActive())
            <a href="{{ $clearUrl }}" class="text-xs text-gray-500 dark:text-gray-400 hover:underline py-1">Reset</a>
            @endif
        </form>
    </details>
    <script>
        (function (details) {
            var desktop = window.matchMedia('(min-width: 768px)');
            var sync = function () { if (desktop.matches) details.open = true; };
            sync();
            desktop.addEventListener('change', sync);
        })(document.currentScript.previousElementSibling);
    </script>
</div>
