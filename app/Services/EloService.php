<?php

// app/Services/EloService.php
namespace App\Services;

class EloService
{
    public function getEloGrade($elo)
    {
        $eloRanges = [
            'D' => [1000, 1999],
            'C' => [2000, 2999],
            'B' => [3000, 3999],
            'A' => [4000, 4999],
            'S' => [5000, 5999],
        ];

        foreach ($eloRanges as $grade => $range) {
            if ($elo >= $range[0] && $elo <= $range[1]) {
                return $grade;
            }
        }
    }
}
