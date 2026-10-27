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

    /**
     * iCCup's points table: [your bracket][opponent's bracket] => [win, loss].
     * D, C and B each cover their -/+ grades (E counts as D); A-, A, A+ and S
     * are brackets of their own. Not zero-sum: an underdog gains more than
     * the favourite loses, and beating a much stronger player costs them
     * nothing.
     */
    private const POINTS = [
        'D' => ['D' => [100, 50], 'C' => [125, 37], 'B' => [150, 25], 'A-' => [175, 12], 'A' => [200, 0], 'A+' => [200, 0], 'S' => [200, 0]],
        'C' => ['D' => [75, 62], 'C' => [100, 50], 'B' => [125, 37], 'A-' => [150, 25], 'A' => [175, 12], 'A+' => [200, 0], 'S' => [200, 0]],
        'B' => ['D' => [50, 75], 'C' => [75, 62], 'B' => [100, 50], 'A-' => [125, 37], 'A' => [150, 25], 'A+' => [175, 12], 'S' => [200, 0]],
        'A-' => ['D' => [25, 131], 'C' => [50, 112], 'B' => [75, 93], 'A-' => [100, 75], 'A' => [125, 56], 'A+' => [150, 37], 'S' => [175, 25]],
        'A' => ['D' => [10, 150], 'C' => [25, 131], 'B' => [50, 112], 'A-' => [75, 93], 'A' => [100, 75], 'A+' => [125, 56], 'S' => [150, 37]],
        'A+' => ['D' => [10, 150], 'C' => [10, 150], 'B' => [25, 131], 'A-' => [50, 112], 'A' => [75, 93], 'A+' => [100, 75], 'S' => [125, 56]],
        'S' => ['D' => [10, 200], 'C' => [10, 200], 'B' => [10, 200], 'A-' => [25, 175], 'A' => [50, 150], 'A+' => [75, 125], 'S' => [100, 100]],
    ];

    /**
     * Points a player at $elo gains (win) or loses (negative) against an
     * opponent at $opponentElo. In team games $opponentElo is the other
     * team's average.
     */
    public function points($elo, $opponentElo, bool $won): int
    {
        [$win, $loss] = self::POINTS[$this->bracket($elo)][$this->bracket($opponentElo)];

        return $won ? $win : -$loss;
    }

    /**
     * Each player's change for one game: own rank against the other team's
     * average. Ratings stop at 0, so a change never takes more than the
     * player has and reversing it restores them exactly.
     *
     * @param  array<int, int|float>  $winners  id => elo before the game
     * @param  array<int, int|float>  $losers  id => elo before the game
     * @return array<int, int>  id => change
     */
    public function rateGame(array $winners, array $losers): array
    {
        $average = fn(array $team) => $team ? array_sum($team) / count($team) : 1000;
        $winnerAverage = $average($winners);
        $loserAverage = $average($losers);

        $changes = [];
        foreach ($winners as $id => $elo) {
            $changes[$id] = $this->points($elo, $loserAverage, true);
        }
        foreach ($losers as $id => $elo) {
            $changes[$id] = max($this->points($elo, $winnerAverage, false), (int) -$elo);
        }

        return $changes;
    }

    private function bracket($elo): string
    {
        $grade = $this->getEloGrade($elo);

        return in_array($grade, ['A-', 'A', 'A+', 'S'], true) ? $grade : str_replace('E', 'D', $this->getGradeTier($grade));
    }

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
