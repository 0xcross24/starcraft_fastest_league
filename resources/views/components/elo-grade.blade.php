@props(['elo' => 0])

@php
    $eloService = app(\App\Services\EloService::class);
    $grade = $eloService->getEloGrade($elo);

    /*
     * Same badge as the Discord ladder emojis (docs/discord-badges): a black
     * box with the border and letter in the tier colour. The colours are
     * inline styles, so nothing here depends on Tailwind keeping a class.
     */
    $gradeColors = [
        'S' => '#FFC800',
        'A' => '#3CE03C',
        'B' => '#88B4D4',
        'C' => '#F0D840',
        'D' => '#FF0000',
        'E' => '#A0A0A0',
    ];

    $color = $gradeColors[$eloService->getGradeTier($grade)] ?? '#A0A0A0';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center justify-center h-6 min-w-[2.25rem] px-1 pt-[3px] rounded-sm border-2 bg-black font-mono font-bold leading-none']) }}
      style="color: {{ $color }}; border-color: {{ $color }};">{{ $grade }}</span>
