<?php

namespace App\Scanning;

use App\Enums\Severity;

/**
 * Turns open findings into a 0-100 score and an A-F grade.
 */
class Scoring
{
    /**
     * @param  iterable<Severity>  $severities  one entry per open finding
     * @return array{score: int, grade: string}
     */
    public static function calculate(iterable $severities): array
    {
        $score = 100;
        $hasCritical = false;

        foreach ($severities as $severity) {
            $score -= $severity->penalty();
            $hasCritical = $hasCritical || $severity === Severity::Critical;
        }

        $score = max(0, $score);
        $grade = self::grade($score);

        // Any open critical problem caps the grade at D, however good the rest is.
        if ($hasCritical && in_array($grade, ['A', 'B', 'C'], true)) {
            $grade = 'D';
        }

        return ['score' => $score, 'grade' => $grade];
    }

    public static function grade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 65 => 'C',
            $score >= 50 => 'D',
            default => 'F',
        };
    }

    public static function gradeColor(?string $grade): string
    {
        return match ($grade) {
            'A' => 'green',
            'B' => 'lime',
            'C' => 'yellow',
            'D' => 'orange',
            'F' => 'red',
            default => 'zinc',
        };
    }
}
