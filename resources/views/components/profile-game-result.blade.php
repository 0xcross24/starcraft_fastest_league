@props(['replayId', 'own', 'won', 'team', 'opponents'])

{{-- One line per game on a profile, so wins and losses can be scanned down the list:
     the result, teammate(s) vs opponents, when, and this player's points. --}}
<div class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 rounded-t border-l-4 {{ $won ? 'border-emerald-500 bg-emerald-900/20' : 'border-red-500 bg-red-900/20' }}">
    <span class="rounded px-2 py-0.5 text-xs font-bold tracking-wide {{ $won ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white' }}">{{ $won ? 'WIN' : 'LOST' }}</span>
    <span class="text-sm text-gray-200">
        {{-- Teammates only: this profile's player is implied --}}
        @foreach($team->reject(fn($p) => $p->is($own)) as $player)
        <a href="{{ route('player', ['user' => $player->player_name]) }}" class="text-neonBlue hover:underline">{{ $player->player_name }}</a>{{ $loop->last ? '' : ' + ' }}
        @endforeach
        <span class="mx-1 text-gray-400">vs</span>
        @foreach($opponents as $player)
        <a href="{{ route('player', ['user' => $player->player_name]) }}" class="text-neonBlue hover:underline">{{ $player->player_name }}</a>@if(! $loop->last) + @endif
        @endforeach
    </span>
    <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($own?->start_time ?? $own?->created_at)->format('Y-m-d H:i') }}</span>
    @if($own)
    <span class="text-sm font-semibold {{ $own->points >= 0 ? 'text-emerald-400' : 'text-red-400' }}">{{ $own->points >= 0 ? '+' : '' }}{{ $own->points }}</span>
    @endif
    <span class="ml-auto text-xs text-gray-500">Replay <span class="font-mono">{{ substr($replayId, 0, 8) }}</span></span>
</div>
