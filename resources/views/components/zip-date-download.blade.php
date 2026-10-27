@props(['season', 'format' => null, 'player' => null, 'label' => 'By date'])

{{-- Same zip as x-zip-download-button, limited to games played between two dates (both optional, inclusive). --}}
<details class="relative text-xs" @if(session('error')) open @endif>
    <summary class="inline-flex items-center gap-1 px-3 py-1 cursor-pointer select-none rounded border border-blue-600 text-blue-600 dark:text-blue-400 font-semibold hover:bg-blue-600 hover:text-white transition">
        {{ $label }}&hellip;
    </summary>
    <form method="GET" action="{{ route('replays.download-zip') }}"
        class="absolute right-0 z-10 mt-2 flex flex-wrap items-end gap-2 p-3 w-max max-w-[90vw] rounded-lg shadow-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
        <input type="hidden" name="season" value="{{ $season }}">
        @if($format)<input type="hidden" name="format" value="{{ $format }}">@endif
        @if($player)<input type="hidden" name="player" value="{{ $player }}">@endif
        <label class="flex flex-col gap-1 text-gray-600 dark:text-gray-300">
            From
            <input type="date" name="from" value="{{ request('from') }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-xs py-1">
        </label>
        <label class="flex flex-col gap-1 text-gray-600 dark:text-gray-300">
            To
            <input type="date" name="to" value="{{ request('to') }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-xs py-1">
        </label>
        <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition font-semibold">Download .zip</button>
        @if(session('error'))
        <p class="basis-full text-red-500">{{ session('error') }}</p>
        @endif
    </form>
</details>
