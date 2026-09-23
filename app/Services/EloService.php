<?php

// app/Services/EloService.php
namespace App\Services;

class EloService
{
    /**
     * Lower bound of each grade, highest first. A cascade rather than
     * [min, max] pairs: the old table topped out at 6999, so an elo of 7000
     * matched nothing and fell through to the 'E' fallback, showing the
     * strongest players as the weakest grade.
     */
    private const GRADE_FLOORS = [
        'S' => 6500,
        'A+' => 6000,
        'A' => 5500,
        'A-' => 5000,
        'B+' => 4500,
        'B' => 4000,
        'B-' => 3500,
        'C+' => 3000,
        'C' => 2500,
        'C-' => 2000,
        'D+' => 1500,
        'D' => 900,
        'D-' => 500,
    ];

    public function getEloGrade($elo): string
    {
        $elo = (float) ($elo ?? 0);

        foreach (self::GRADE_FLOORS as $grade => $floor) {
            if ($elo >= $floor) {
                return $grade;
            }
        }

        return 'E';
    }

    /**
     * The grade without its +/- suffix: 'A+' and 'A-' are both 'A'.
     *
     * Presentation deliberately lives in the <x-elo-grade> component instead
     * of here. This class used to return Tailwind classes directly, but
     * tailwind.config.js only scans resources/views, so those names were
     * invisible to the build and got purged the next time assets were
     * rebuilt - the rank letters lost their colour on production.
     */
    public function getGradeTier($grade): string
    {
        return rtrim((string) $grade, '+-') ?: 'E';
    }
}
