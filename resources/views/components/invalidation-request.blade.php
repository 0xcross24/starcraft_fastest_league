@props(['replayId', 'rows'])

{{-- "Request invalidation" for the players in a game: a modal asks for a reason
     and a justification, and submitting opens a Discord ticket for the admins.
     Once anyone has asked, the players see where it stands and a link to the
     game page, where they agree, dispute or comment. --}}
@php
$user = auth()->user();
$mine = $user ? $rows->where('user_id', $user->id)->first() : null;
$gameInvalid = $rows->isNotEmpty() && $rows->every(fn($r) => $r->is_invalid);
$existing = $mine ? \App\Models\InvalidationRequest::where('replay_id', $replayId)->orderByRaw("status = 'open' desc")->latest('id')->first() : null;
$stances = $existing && $existing->status === 'open' ? app(\App\Services\InvalidationCase::class)->stances($replayId) : collect();
$flash = session('invalidation_request');
$reopen = old('replay_id') === $replayId && $errors->invalidation->isNotEmpty();
$modal = 'invalidate-' . $replayId;
@endphp

@if($mine && !$gameInvalid)
<div class="-mt-4 mb-6 flex flex-col items-center gap-1 text-xs">
    @if(($flash['replay_id'] ?? null) === $replayId)
    <p class="text-emerald-400">{{ $flash['message'] }}</p>
    @endif

    @if($existing)
    <a href="{{ route('games.show', $replayId) }}" class="rounded px-2 py-0.5 hover:underline {{ $existing->status === 'rejected' ? 'bg-red-900/40 text-red-300' : 'bg-amber-900/40 text-amber-300' }}">
        @if($existing->status === 'open')
        Invalidation requested · {{ $stances->filter(fn($s) => $s === 'agree')->count() }} agree · {{ $stances->filter(fn($s) => $s === 'dispute')->count() }} dispute ·
        {{ ($stances[$user->id] ?? null) ? 'View discussion' : 'Agree or dispute' }} &rarr;
        @else
        Invalidation {{ $existing->status }}@if($existing->resolution_note) · {{ $existing->resolution_note }}@endif · details &rarr;
        @endif
    </a>
    @else
    <button type="button" x-data x-on:click.prevent="$dispatch('open-modal', @js($modal))"
        class="text-gray-400 hover:text-amber-300 underline underline-offset-2">Request invalidation</button>

    <x-modal :name="$modal" :show="$reopen" maxWidth="lg" focusable>
        <form method="post" action="{{ route('invalidation-requests.store', $replayId) }}" class="p-6 text-left text-sm">
            @csrf
            <input type="hidden" name="replay_id" value="{{ $replayId }}">
            <h2 class="text-lg font-semibold text-gray-100">Request invalidation of game <span class="font-mono">{{ substr($replayId, 0, 8) }}</span></h2>
            <p class="mt-1 text-gray-400">The other players in the game can agree or dispute it. If everyone agrees it's approved automatically; otherwise an admin decides. If it's approved, the game is removed and the ELO and wins/losses it gave every player are reversed.</p>

            <label for="reason-{{ $replayId }}" class="block mt-4 font-medium text-gray-300">Reason</label>
            <select id="reason-{{ $replayId }}" name="reason" required class="mt-1 w-full rounded-md border-gray-700 bg-gray-900 text-gray-200">
                <option value="" disabled @selected(!$reopen || !old('reason'))>Choose a reason…</option>
                @foreach(\App\Models\InvalidationRequest::REASONS as $key => $label)
                <option value="{{ $key }}" @selected($reopen && old('reason') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->invalidation->get('reason')" class="mt-1" />

            <label for="justification-{{ $replayId }}" class="block mt-4 font-medium text-gray-300">Justification</label>
            <textarea id="justification-{{ $replayId }}" name="justification" rows="5" required minlength="20" maxlength="2000"
                placeholder="What happened in this game, and why should it not count? Include timestamps or anything the admins should check in the replay."
                class="mt-1 w-full rounded-md border-gray-700 bg-gray-900 text-gray-200">{{ $reopen ? old('justification') : '' }}</textarea>
            <x-input-error :messages="$errors->invalidation->get('justification')" class="mt-1" />

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Cancel</x-secondary-button>
                <x-primary-button>Submit request</x-primary-button>
            </div>
        </form>
    </x-modal>
    @endif
</div>
@elseif(($flash['replay_id'] ?? null) === $replayId)
<p class="-mt-4 mb-6 text-center text-xs text-emerald-400">{{ $flash['message'] }}</p>
@endif
