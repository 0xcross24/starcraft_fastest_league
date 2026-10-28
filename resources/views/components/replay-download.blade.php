@props(['id'])

{{-- One game's download button, plus a "Select" box that x-zip-selected-download
     reveals (and collects) when the page offers a zip of the selected games. --}}
<div class="w-full flex items-center justify-center gap-4 py-2 mb-6">
    <a href="{{ route('replay.download', ['uuid' => $id]) }}" class="inline-block px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition font-semibold">Download Replay</a>
    <label class="js-zip-pick-label items-center gap-1 text-xs text-gray-600 dark:text-gray-300 cursor-pointer select-none" style="display: none">
        <input type="checkbox" class="js-zip-pick rounded border-gray-400 dark:bg-gray-900 text-blue-600" value="{{ $id }}">
        Select
    </label>
</div>
