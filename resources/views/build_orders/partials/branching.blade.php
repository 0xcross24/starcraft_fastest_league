@php
    $current = $buildOrder ?? null;
    $selectedParent = old('parent_id', $current->parent_id ?? null);
    $selectedPhase = old('phase', $current->phase ?? null);
    $selectedPosition = old('position', $current->position ?? 0);
@endphp

<div class="rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 p-4 space-y-4">
    <div class="flex items-center gap-2">
        <svg class="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 3v12"></path>
            <circle cx="18" cy="6" r="3"></circle>
            <circle cx="6" cy="18" r="3"></circle>
            <path d="M18 9a9 9 0 0 1-9 9"></path>
        </svg>
        <span class="font-semibold font-nav text-gray-900 dark:text-gray-100">Branching</span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="sm:col-span-2">
            <label for="parent_id" class="block font-semibold font-nav">Continues from</label>
            <select name="parent_id" id="parent_id" class="w-full border rounded px-3 py-2 text-gray-900">
                <option value="">None &mdash; this is an opener</option>
                @foreach($parents as $parent)
                <option value="{{ $parent->id }}" {{ (string) $selectedParent === (string) $parent->id ? 'selected' : '' }}>
                    {{ $parent->race }} &mdash; {{ $parent->title }}
                </option>
                @endforeach
            </select>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Leave as None to list this build at the top level.</div>
            @error('parent_id')<div class="text-red-600 text-xs">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="phase" class="block font-semibold font-nav">Phase</label>
            <select name="phase" id="phase" class="w-full border rounded px-3 py-2 text-gray-900">
                <option value="">Not set</option>
                @foreach($phases as $phase)
                <option value="{{ $phase }}" {{ $selectedPhase === $phase ? 'selected' : '' }}>{{ $phase }}</option>
                @endforeach
            </select>
            @error('phase')<div class="text-red-600 text-xs">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="position" class="block font-semibold font-nav">Order</label>
            <input type="number" name="position" id="position" min="0" class="w-full border rounded px-3 py-2 text-gray-900" value="{{ $selectedPosition }}">
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Among siblings.</div>
            @error('position')<div class="text-red-600 text-xs">{{ $message }}</div>@enderror
        </div>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        A build cannot continue from itself or from anything further down its own chain, so those are left out of the list.
    </p>
</div>
