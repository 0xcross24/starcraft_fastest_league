<x-app-layout>
    <div class="py-12">
        <div class="max-w-screen-md mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h1 class="text-2xl font-bold mb-6 font-logo">Edit Build Order</h1>
                    <form action="{{ route('builds.update', ['id' => $buildOrder->id]) }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="title" class="block font-semibold font-nav">Title</label>
                            <input type="text" name="title" id="title" class="w-full border rounded px-3 py-2 text-gray-900" value="{{ old('title', $buildOrder->title) }}" required>
                            @error('title')<div class="text-red-600 text-xs">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="youtube_url" class="block font-semibold font-nav">YouTube Link (optional)</label>
                            <input type="text" name="youtube_url" id="youtube_url" class="w-full border rounded px-3 py-2 text-gray-900" value="{{ old('youtube_url', $buildOrder->youtube_url) }}" placeholder="https://www.youtube.com/watch?v=...">
                            @error('youtube_url')<div class="text-red-600 text-xs">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="race" class="block font-semibold font-nav">Race</label>
                            <select name="race" id="race" class="w-full border rounded px-3 py-2 text-gray-900" required>
                                <option value="">Select Race</option>
                                <option value="Terran" {{ old('race', $buildOrder->race) == 'Terran' ? 'selected' : '' }}>Terran</option>
                                <option value="Protoss" {{ old('race', $buildOrder->race) == 'Protoss' ? 'selected' : '' }}>Protoss</option>
                                <option value="Zerg" {{ old('race', $buildOrder->race) == 'Zerg' ? 'selected' : '' }}>Zerg</option>
                            </select>
                            @error('race')<div class="text-red-600 text-xs">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="matchup" class="block font-semibold font-nav">Matchup</label>
                            <select name="matchup[]" id="matchup" class="w-full border rounded px-3 py-2 text-gray-900" multiple required>
                                <option value="PPP" {{ in_array('PPP', $buildOrder->matchup ?? []) ? 'selected' : '' }}>PPP</option>
                                <option value="PPT" {{ in_array('PPT', $buildOrder->matchup ?? []) ? 'selected' : '' }}>PPT</option>
                                <option value="PPZ" {{ in_array('PPZ', $buildOrder->matchup ?? []) ? 'selected' : '' }}>PPZ</option>
                                <option value="PTZ" {{ in_array('PTZ', $buildOrder->matchup ?? []) ? 'selected' : '' }}>PTZ</option>
                                <option value="PP" {{ in_array('PP', $buildOrder->matchup ?? []) ? 'selected' : '' }}>PP</option>
                                <option value="PT" {{ in_array('PT', $buildOrder->matchup ?? []) ? 'selected' : '' }}>PT</option>
                                <option value="PZ" {{ in_array('PZ', $buildOrder->matchup ?? []) ? 'selected' : '' }}>PZ</option>
                                <option value="TZ" {{ in_array('TZ', $buildOrder->matchup ?? []) ? 'selected' : '' }}>TZ</option>
                            </select>
                            <div class="text-xs text-gray-500 mt-1">Hold Ctrl (Windows) or Command (Mac) to select multiple.</div>
                            @error('matchup')<div class="text-red-600 text-xs">{{ $message }}</div>@enderror
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const raceSelect = document.getElementById('race');
                                const matchupSelect = document.getElementById('matchup');
                                const allOptions = Array.from(matchupSelect.options);

                                function filterMatchups() {
                                    const race = raceSelect.value;
                                    matchupSelect.innerHTML = '';
                                    matchupSelect.appendChild(allOptions[0]); // 'Select Matchup'
                                    let allowed = [];
                                    if (race === 'Terran') {
                                        allowed = ['PPT', 'PTZ', 'PT', 'TZ'];
                                    } else if (race === 'Zerg') {
                                        allowed = ['PPZ', 'PZ', 'TZ'];
                                    } else if (race === 'Protoss') {
                                        allowed = ['PPP', 'PPT', 'PPZ', 'PTZ', 'PP', 'PT', 'PZ']; // everything but TZ
                                    } else {
                                        allowed = allOptions.slice(1).map(o => o.value);
                                    }
                                    allowed.forEach(val => {
                                        const opt = allOptions.find(o => o.value === val);
                                        if (opt) {
                                            const node = opt.cloneNode(true);
                                            matchupSelect.appendChild(node);
                                        }
                                    });
                                }
                                raceSelect.addEventListener('change', filterMatchups);
                                filterMatchups();
                            });
                        </script>
                        <div>
                            <label for="description" class="block font-semibold font-nav">Description</label>
                            <textarea name="description" id="description" class="w-full border rounded px-3 py-2 text-gray-900">{{ old('description', $buildOrder->description) }}</textarea>
                            @error('description')<div class="text-red-600 text-xs">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="steps" class="block font-semibold font-nav">Steps</label>
                            <textarea name="steps" id="steps" class="w-full border rounded px-3 py-2 text-gray-900" required>{{ old('steps', $buildOrder->steps) }}</textarea>
                            @error('steps')<div class="text-red-600 text-xs">{{ $message }}</div>@enderror
                        </div>
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('builds.index') }}" class="px-4 py-2 bg-gray-300 rounded text-gray-900">Cancel</a>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
