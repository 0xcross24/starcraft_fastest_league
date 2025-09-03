<x-app-layout>
    <h1 class="text-2xl font-bold mb-4">Edit Build Order</h1>
    <form method="POST" action="{{ route('builds.update', $buildOrder->id) }}">
        @csrf
        @method('PUT')
        <div>
            <label>Title</label>
            <input type="text" name="title" value="{{ $buildOrder->title }}" required>
        </div>
        <div>
            <label>Description</label>
            <textarea name="description" required>{{ $buildOrder->description }}</textarea>
        </div>
        <div>
            <label>Race</label>
            <input type="text" name="race" value="{{ $buildOrder->race }}" required>
        </div>
        <div>
            <label>Matchup</label>
            <input type="text" name="matchup" value="{{ is_array($buildOrder->matchup) ? implode(', ', $buildOrder->matchup) : $buildOrder->matchup }}" required>
        </div>
        <div>
            <label>Steps</label>
            <textarea name="steps" required>{{ $buildOrder->steps }}</textarea>
        </div>
        <div>
            <label>YouTube URL</label>
            <input type="text" name="youtube_url" value="{{ $buildOrder->youtube_url }}">
        </div>
        <button type="submit">Update</button>
    </form>
    <a href="{{ route('builds.index') }}" class="mt-4 inline-block text-blue-600">Back to list</a>
</x-app-layout>
