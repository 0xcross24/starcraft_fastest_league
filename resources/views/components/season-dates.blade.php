@props(['season'])
@php
$start = $season->starts_at;
$end = $season->endsAt();
$daysLeft = $season->is_active && $end ? (int) floor(now()->diffInDays($end)) : null;
@endphp
@if($start || $end)
<p {{ $attributes->merge(['class' => 'text-sm text-gray-500 dark:text-gray-400']) }}>
    @if($start)<time datetime="{{ $start->toIso8601String() }}">{{ $start->format('M j, Y') }}</time>@else Ends @endif
    @if($start && $end) – @endif
    @if($end)<time datetime="{{ $end->toIso8601String() }}" title="{{ $end->format('M j, Y H:i') }} UTC">{{ $end->format('M j, Y') }}</time>@endif
    @if($daysLeft !== null)
    · {{ $daysLeft > 0 ? 'ends in ' . $daysLeft . ' ' . \Illuminate\Support\Str::plural('day', $daysLeft) : 'ends today' }}
    @endif
</p>
@endif
