<?php

// app/Services/EloService.php
namespace App\Services;

class EloService
{
    public function getEloGrade($elo)
    {
        $eloRanges = [
            'E' => [0, 499],
            'D-' => [500, 899],
            'D' => [900, 1499],
            'D+' => [1500, 1999],
            'C-' => [2000, 2499],
            'C' => [2500, 2999],
            'C+' => [3000, 3499],
            'B-' => [3500, 3999],
            'B' => [4000, 4499],
            'B+' => [4500, 4999],
            'A-' => [5000, 5499],
            'A' => [5500, 5999],
            'A+' => [6000, 6499],
            'S' => [6500, 6999],
        ];

        foreach ($eloRanges as $grade => $range) {
            if ($elo >= $range[0] && $elo <= $range[1]) {
                return $grade;
            }
        }
    }
}
