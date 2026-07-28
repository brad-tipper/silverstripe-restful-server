<?php

namespace BradTipper\RestfulServer\Auth;

/**
 * Entropy-based password strength evaluator.
 *
 * Returns a score from 0 (very weak) to 4 (very strong) plus an entropy
 * estimate in bits so the client can display a live meter.
 */
class PasswordStrength
{
    /**
     * Evaluate a password and return a strength assessment.
     *
     * @return array{score: int, entropy: float, label: string, feedback: string}
     */
    public static function evaluate(string $password): array
    {
        $length = strlen($password);
        if ($length === 0) {
            return self::result(0, 0, 'Empty', 'Enter a password.');
        }

        $charset = 0;
        if (preg_match('/[a-z]/', $password)) $charset += 26;
        if (preg_match('/[A-Z]/', $password)) $charset += 26;
        if (preg_match('/[0-9]/', $password)) $charset += 10;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $charset += 33;

        $entropy = $length * log(max($charset, 1), 2);

        // Entropy thresholds (NIST-inspired, relaxed for practical UX)
        $score = match (true) {
            $entropy < 28 => 0,
            $entropy < 40 => 1,
            $entropy < 56 => 2,
            $entropy < 72 => 3,
            default => 4,
        };

        $label = match ($score) {
            0 => 'Very weak',
            1 => 'Weak',
            2 => 'Fair',
            3 => 'Strong',
            default => 'Very strong',
        };

        $feedback = match (true) {
            $length < 8 => 'Password is too short. Use at least 8 characters.',
            $length < 12 && $score < 2 => 'A longer password would be stronger.',
            $score < 2 => 'Add uppercase letters, numbers, or symbols.',
            $score < 3 => 'A few more characters would make this password very strong.',
            default => 'Excellent password.',
        };

        return self::result($score, $entropy, $label, $feedback);
    }

    private static function result(int $score, float $entropy, string $label, string $feedback): array
    {
        return [
            'score' => $score,
            'entropy' => round($entropy, 1),
            'label' => $label,
            'feedback' => $feedback,
        ];
    }
}