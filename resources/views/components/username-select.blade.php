@props(['names', 'selected', 'urlFor'])

{{-- Switches between the player names that belong to one account. --}}
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
    <span>Username</span>
    <select onchange="window.location.href = this.value"
        class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 text-sm py-1 pr-8">
        @foreach($names as $name)
        <option value="{{ $urlFor($name) }}" @selected($name->id === $selected->id)>{{ $name->name }}</option>
        @endforeach
    </select>
</label>
