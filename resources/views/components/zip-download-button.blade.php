@props(['season', 'format' => null, 'player' => null, 'filters' => [], 'count' => null, 'label' => 'Download all'])

{{-- Download every listed game as one zip. Guests are sent to log in first, then back here (see App\Http\Middleware\Authenticate). --}}
<a href="{{ route('replays.download-zip', array_merge(array_filter(['season' => $season, 'format' => $format, 'player' => $player]), $filters)) }}"
    class="inline-flex items-center gap-1 px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition font-semibold">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" />
    </svg>
    {{ $label }}{{ $count ? " ({$count})" : '' }} .zip
</a>
