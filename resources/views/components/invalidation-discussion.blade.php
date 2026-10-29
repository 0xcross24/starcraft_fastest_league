@props(['replayId', 'requests', 'stances', 'players', 'comments', 'open' => false, 'canPost' => false, 'adminReply' => false, 'replyAction' => null])

{{-- One game's invalidation requests, where each player stands, and the
     conversation between the players and the admins. Players go by the name
     they played under in this game ($players: user id => name). --}}
@php
$decided = $requests->firstWhere('status', '!=', 'open');
$stanceLabel = ['agree' => ['agrees', 'text-emerald-400'], 'dispute' => ['disputes', 'text-red-400']];
@endphp
<div class="space-y-4 text-sm">
    @if($requests->isNotEmpty())
    <div class="flex flex-wrap items-center gap-2">
        <h2 class="text-lg font-semibold">Invalidation request</h2>
        @if($open)
        <span class="rounded bg-amber-900/60 px-2 py-0.5 text-xs text-amber-200">open</span>
        @elseif($decided)
        <span class="rounded px-2 py-0.5 text-xs {{ $decided->status === 'approved' ? 'bg-emerald-900 text-emerald-200' : 'bg-red-900 text-red-200' }}">{{ $decided->status }}</span>
        @endif
    </div>

    @foreach($requests as $req)
    <div class="rounded border border-gray-700 p-3">
        <div class="text-xs text-gray-400">{{ $players[$req->user_id] ?? $req->user?->player_name }} asked · {{ $req->created_at->format('Y-m-d H:i') }}</div>
        <div class="font-semibold text-gray-200">{{ $req->reasonLabel() }}</div>
        <p class="mt-1 whitespace-pre-line text-gray-300">{{ $req->justification }}</p>
    </div>
    @endforeach
    @endif

    {{-- Once decided, only the players who answered are worth listing --}}
    @php $shown = $open ? $stances : $stances->filter(); @endphp
    @if($shown->isNotEmpty())
    <div>
        <div class="font-semibold text-gray-300">Players</div>
        <ul class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
            @foreach($shown as $userId => $stance)
            <li>{{ $players[$userId] ?? '?' }}:
                @if($stance)<span class="{{ $stanceLabel[$stance][1] }}">{{ $stanceLabel[$stance][0] }}</span>@else<span class="text-gray-400">no answer yet</span>@endif
            </li>
            @endforeach
        </ul>
        @if($open)
        <p class="mt-1 text-xs text-gray-400">If every player agrees, the game is invalidated automatically. If anyone disputes, an admin decides.</p>
        @endif
    </div>
    @endif

    @if($comments->isNotEmpty())
    <ul class="space-y-2">
        @foreach($comments as $c)
        <li class="rounded p-2 {{ $c->from_admin ? 'bg-blue-900/30 border border-blue-800' : 'bg-gray-900/40' }}">
            <div class="text-xs text-gray-400">
                <span class="text-gray-200">{{ $c->from_admin ? $c->user?->player_name : ($players[$c->user_id] ?? $c->user?->player_name) }}</span>
                @if($c->from_admin)<span class="ml-1 text-blue-300">admin</span>@endif
                @if($c->stance)<span class="ml-1 {{ $stanceLabel[$c->stance][1] }}">{{ $stanceLabel[$c->stance][0] }}</span>@endif
                · {{ $c->created_at->format('Y-m-d H:i') }}
            </div>
            @if($c->body)<p class="mt-1 whitespace-pre-line text-gray-200">{{ $c->body }}</p>@endif
        </li>
        @endforeach
    </ul>
    @endif

    @if($decided && !$open)
    <p class="text-gray-300">
        {{ $decided->status === 'approved' ? 'Approved: the game was invalidated.' : 'Rejected: the game still counts.' }}
        @if($decided->resolver) (by {{ $decided->resolver->player_name }}) @endif
        @if($decided->resolution_note) · {{ $decided->resolution_note }} @endif
    </p>
    @endif

    @if($canPost)
    <form method="post" action="{{ route('invalidation-requests.comment', $replayId) }}" class="space-y-2 rounded border border-gray-700 p-3">
        @csrf
        <div class="flex flex-wrap gap-4">
            @foreach(['agree' => 'I agree, invalidate it', 'dispute' => 'I dispute this', '' => 'Just a comment'] as $value => $label)
            <label class="flex items-center gap-1">
                <input type="radio" name="stance" value="{{ $value }}" @checked(old('stance', '') === $value) class="bg-gray-900 border-gray-600">
                <span>{{ $label }}</span>
            </label>
            @endforeach
        </div>
        <textarea name="body" rows="3" maxlength="2000" placeholder="Explain (needed for a dispute or a comment)"
            class="w-full rounded-md border-gray-700 bg-gray-900 text-gray-200">{{ old('body') }}</textarea>
        <x-input-error :messages="$errors->discussion->all()" />
        <x-primary-button>Post</x-primary-button>
    </form>
    @endif

    @if($adminReply)
    <form method="post" action="{{ $replyAction ?? route('admin.invalidation-requests.reply', $replayId) }}" class="space-y-2 rounded border border-blue-800 p-3">
        @csrf
        <textarea name="body" rows="2" maxlength="2000" required placeholder="Reply to the players (emailed to them and posted on Discord)"
            class="w-full rounded-md border-gray-700 bg-gray-900 text-gray-200"></textarea>
        <div class="flex flex-wrap items-center gap-3">
            <x-primary-button>Reply as admin</x-primary-button>
            <a href="{{ route('admin.invalidation-requests.index') }}" class="text-xs text-blue-400 hover:underline">Approve or reject in Admin → Requests</a>
        </div>
    </form>
    @endif
</div>
