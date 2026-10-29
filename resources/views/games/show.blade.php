<x-app-layout>
    @php
    $first = $rows->first();
    $invalid = $rows->every(fn($r) => $r->is_invalid);
    @endphp
    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                <h1 class="text-xl font-semibold">Game <span class="font-mono">{{ substr($replayId, 0, 8) }}</span></h1>
                <p class="text-sm text-gray-400">
                    Season {{ $first->season_id }} · {{ $first->format }} · played {{ \Carbon\Carbon::parse($first->start_time ?? $first->created_at)->format('Y-m-d H:i') }}
                    @if($invalid)<span class="ml-2 rounded bg-red-900 px-2 py-0.5 text-xs text-red-200">invalidated</span>@endif
                </p>

                @if (session('status'))
                <div class="mt-4 rounded bg-emerald-900/40 border border-emerald-700 px-4 py-2 text-sm text-emerald-300">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                <div class="mt-4 rounded bg-red-900/40 border border-red-700 px-4 py-2 text-sm text-red-300">{{ session('error') }}</div>
                @endif

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    @foreach($rows->groupBy('team') as $team => $teamRows)
                    <div>
                        <div class="font-semibold {{ $teamRows->first()->winning_team ? 'text-emerald-400' : 'text-red-400' }}">
                            Team {{ $team }} {{ $teamRows->first()->winning_team ? 'won' : 'lost' }}
                        </div>
                        @foreach($teamRows as $row)
                        <div class="flex justify-between gap-2 border-b border-gray-700 py-1">
                            <a href="{{ route('player', ['user' => $row->player_name]) }}" class="truncate text-blue-400 hover:underline">{{ $row->player_name }}</a>
                            <span class="text-gray-400">{{ $row->race }}</span>
                            <span class="{{ $row->points >= 0 ? 'text-emerald-400' : 'text-red-400' }}">{{ $row->points >= 0 ? '+' : '' }}{{ $row->points }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endforeach
                </div>
                <div class="mt-4"><x-replay-download :id="$replayId" /></div>
                @if($isPlayer && $requests->isEmpty())
                <x-invalidation-request :replay-id="$replayId" :rows="$rows" />
                @endif
            </div>

            @if($requests->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                <x-invalidation-discussion :replay-id="$replayId" :requests="$requests" :stances="$stances" :players="$players" :comments="$comments"
                    :open="$open" :can-post="$canPost" :admin-reply="!$isPlayer && $open && auth()->user()?->isAdmin()" />
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
