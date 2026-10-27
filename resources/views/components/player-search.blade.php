{{-- Player search box. A plain GET form, so it works without JavaScript;
     Alpine adds suggestions as you type, with arrow keys and Enter to pick. --}}
<form method="GET" action="{{ route('players.search') }}" role="search"
    x-data="{
        q: @js(request()->routeIs('players.search') ? request('q', '') : ''),
        results: [],
        open: false,
        active: -1,
        async fetch() {
            const q = this.q.trim();
            if (q === '') { this.results = []; this.open = false; return; }
            const res = await window.fetch('{{ route('players.search') }}?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
            if (!res.ok || q !== this.q.trim()) return;
            this.results = await res.json();
            this.active = -1;
            this.open = true;
        },
        move(step) {
            if (!this.results.length) return;
            this.open = true;
            this.active = (this.active + step + this.results.length) % this.results.length;
        },
        pick(event) {
            if (this.open && this.active >= 0) {
                event.preventDefault();
                window.location = this.results[this.active].url;
            }
        },
    }"
    @click.outside="open = false"
    @keydown.escape="open = false"
    {{ $attributes->merge(['class' => 'relative']) }}>
    <label for="{{ $id = 'player-search-'.uniqid() }}" class="sr-only">Search players</label>
    <input id="{{ $id }}" name="q" type="search" placeholder="Search players" autocomplete="off" maxlength="50"
        x-model="q"
        @input.debounce.200ms="fetch()"
        @focus="if (results.length) open = true"
        @keydown.arrow-down.prevent="move(1)"
        @keydown.arrow-up.prevent="move(-1)"
        @keydown.enter="pick($event)"
        class="w-full rounded-md border-gray-600 bg-gray-900 text-sm text-gray-100 placeholder-gray-500 focus:border-blue-500 focus:ring-blue-500" />

    <ul x-show="open" x-cloak
        class="absolute z-50 mt-1 w-full overflow-hidden rounded-md border border-gray-700 bg-gray-800 shadow-lg">
        <template x-for="(result, i) in results" :key="result.url">
            <li>
                <a :href="result.url" x-text="result.name" @mouseenter="active = i"
                    :class="i === active ? 'bg-blue-600 text-white' : 'text-gray-200'"
                    class="block px-3 py-2 text-sm"></a>
            </li>
        </template>
        <li x-show="results.length === 0" class="px-3 py-2 text-sm text-gray-400">No players found</li>
    </ul>
</form>
