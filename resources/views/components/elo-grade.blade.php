@props(['elo' => 0])

@php
    $eloService = app(\App\Services\EloService::class);
    $grade = $eloService->getEloGrade($elo);

    /*
     * These class names must appear literally in a Blade file. tailwind.config.js
     * scans resources/views only, so a class returned from app/ is invisible to
     * the build and gets purged - which is exactly how the rank letters lost
     * their colour when assets were last rebuilt.
     */
    $gradeColors = [
        'S' => 'text-neonGold',
        'A' => 'text-neonGreen',
        'B' => 'text-neonBlue',
        'C' => 'text-neonYellow',
        'D' => 'text-neonRed',
        'E' => 'text-neonPink',
    ];

    $color = $gradeColors[$eloService->getGradeTier($grade)]
        ?? 'text-yellow-600 dark:text-yellow-400';
@endphp

<span {{ $attributes->merge(['class' => 'font-bold '.$color]) }}>{{ $grade }}</span>
