<x-app-layout>
    <h1 class="text-2xl font-bold mb-4">{{ $buildOrder->title }}</h1>
    <div>
        <strong>Race:</strong> {{ $buildOrder->race }}<br>
        <strong>Matchup:</strong> {{ is_array($buildOrder->matchup) ? implode(', ', $buildOrder->matchup) : $buildOrder->matchup }}<br>
        <strong>Description:</strong> {{ $buildOrder->description }}<br>
        <strong>Steps:</strong> {{ $buildOrder->steps }}<br>
        @if($buildOrder->youtube_url)
        <strong>YouTube:</strong> <a href="{{ $buildOrder->youtube_url }}" target="_blank">{{ $buildOrder->youtube_url }}</a><br>
        @endif
    </div>
    <a href="{{ route('builds.index') }}" class="mt-4 inline-block text-blue-600">Back to list</a>
</x-app-layout>
