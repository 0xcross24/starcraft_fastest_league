<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                @include('admin.partials.nav')

                <div class="flex flex-wrap gap-2 mb-4 text-sm">
                    @foreach(['open' => 'Open', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
                    <a href="{{ route('admin.invalidation-requests.index', ['status' => $key]) }}"
                        class="rounded px-3 py-1 {{ $status === $key ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}">{{ $label }}</a>
                    @endforeach
                </div>

                <div class="space-y-4">
                    @forelse($requests as $ticket)
                    @php
                    $players = $rows[$ticket->replay_id] ?? collect();
                    $first = $players->first();
                    $invalid = $players->isNotEmpty() && $players->every(fn($r) => $r->is_invalid);
                    @endphp
                    <div class="rounded border {{ $ticket->status === 'open' ? 'border-amber-700' : 'border-gray-700' }} p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-400">
                            <div>
                                <span class="font-mono text-gray-300">{{ substr($ticket->replay_id, 0, 8) }}</span>
                                · Season {{ $first?->season_id }} · {{ $first?->format }}
                                · requested {{ $ticket->created_at->format('Y-m-d H:i') }} by
                                <span class="text-gray-200">{{ $ticket->playerName?->name ?? $ticket->user?->player_name }}</span>
                                <span class="ml-1 rounded px-2 py-0.5 {{ ['open' => 'bg-amber-900 text-amber-200', 'approved' => 'bg-emerald-900 text-emerald-200', 'rejected' => 'bg-red-900 text-red-200'][$ticket->status] ?? '' }}">{{ $ticket->status }}</span>
                                @if($invalid)<span class="ml-1 rounded bg-red-900 px-2 py-0.5 text-red-200">game invalid</span>@endif
                                @unless($ticket->discord_thread_id)<span class="ml-1 text-amber-400" title="Check DISCORD_TICKETS_WEBHOOK_URL and the log">not posted to Discord</span>@endunless
                            </div>
                            <a href="{{ route('replay.download', ['uuid' => $ticket->replay_id]) }}" class="text-blue-400 hover:underline">Download</a>
                        </div>

                        <div class="mt-2 text-sm">
                            <div class="font-semibold text-gray-200">{{ $ticket->reasonLabel() }}</div>
                            <p class="mt-1 whitespace-pre-line text-gray-300">{{ $ticket->justification }}</p>
                        </div>

                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                            @foreach($players->groupBy('team') as $team => $teamRows)
                            <div>
                                <div class="font-semibold {{ $teamRows->first()->winning_team ? 'text-emerald-400' : 'text-red-400' }}">
                                    Team {{ $team }} {{ $teamRows->first()->winning_team ? 'won' : 'lost' }}
                                </div>
                                @foreach($teamRows as $row)
                                <div class="flex justify-between gap-2">
                                    <span class="truncate">{{ $row->player_name }} <span class="text-gray-400">{{ $row->race }}</span></span>
                                    <span class="{{ $row->points >= 0 ? 'text-emerald-400' : 'text-red-400' }}">{{ $row->points >= 0 ? '+' : '' }}{{ $row->points }}</span>
                                </div>
                                @endforeach
                            </div>
                            @endforeach
                        </div>

                        @if($ticket->status === 'open')
                        <form method="post" class="mt-3 flex flex-wrap items-center gap-2">
                            @csrf
                            <x-text-input name="note" placeholder="Note for the players (optional, posted on Discord)" class="flex-1 min-w-0 text-sm" maxlength="1000" />
                            <button formaction="{{ route('admin.invalidation-requests.approve', $ticket) }}"
                                onclick="return confirm(@js('Invalidate game '.substr($ticket->replay_id, 0, 8).'? This reverses the ELO and wins/losses it gave every player.'))"
                                class="rounded bg-red-700 px-3 py-1.5 text-sm text-white hover:bg-red-600">Approve &amp; invalidate</button>
                            <button formaction="{{ route('admin.invalidation-requests.reject', $ticket) }}"
                                class="rounded bg-gray-700 px-3 py-1.5 text-sm text-gray-200 hover:bg-gray-600">Reject</button>
                        </form>
                        @else
                        <p class="mt-2 text-xs text-gray-400">
                            {{ ucfirst($ticket->status) }} by {{ $ticket->resolver?->player_name ?? '?' }} {{ $ticket->resolved_at?->format('Y-m-d H:i') }}@if($ticket->resolution_note): {{ $ticket->resolution_note }}@endif
                        </p>
                        @endif
                    </div>
                    @empty
                    <p class="text-gray-400">No {{ $status === 'all' ? '' : $status }} requests.</p>
                    @endforelse
                </div>

                <div class="mt-4">{{ $requests->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
