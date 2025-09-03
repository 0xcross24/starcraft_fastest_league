<x-app-layout>
    <h1 class="text-2xl font-bold mb-4">Create Build Order</h1>
    <form method="POST" action="{{ route('builds.store') }}">
        @csrf
        <div>
            <label>Title</label>
            <input type="text" name="title" required>
        </div>
        <div>
            <label>Description</label>
            <textarea name="description" required></textarea>
        </div>
        <div>
            <label>Race</label>
            <input type="text" name="race" required>
        </div>
        <div>
            <label>Matchup</label>
            <input type="text" name="matchup" required>
        </div>
        <div>
            <label>Steps</label>
            <textarea name="steps" required></textarea>
        </div>
        <div>
            <label>YouTube URL</label>
            <input type="text" name="youtube_url">
        </div>
        <button type="submit">Create</button>
    </form>
    <a href="{{ route('builds.index') }}" class="mt-4 inline-block text-blue-600">Back to list</a>
</x-app-layout>
