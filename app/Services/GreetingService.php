<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

class GreetingService
{
    /**
     * Get dynamic time-aware, role-aware, and grammatically correct dashboard greeting string.
     */
    public function greeting(?User $user = null): string
    {
        $user = $user ?? auth()->user();
        if ($user === null) {
            return __('Welcome back!');
        }

        $tz = ! empty($user->timezone) ? $user->timezone : (config('app.timezone') !== 'UTC' ? config('app.timezone') : 'Asia/Jakarta');
        $now = CarbonImmutable::now($tz);
        $hour = $now->hour;
        $isLearner = ! $user->isSupervisor() && ! $user->isAdmin() && ! $user->isSuperAdmin();

        $phrases = $this->phrasesForHour($hour, $isLearner);
        $selected = $phrases[array_rand($phrases)];

        $translatedText = __($selected['text']);
        $name = $user->formattedName();

        if ($selected['tone'] === 'question') {
            return "{$translatedText}, {$name}?";
        }

        return "{$translatedText}, {$name}!";
    }

    /**
     * Get candidate greeting phrases for the given local hour.
     *
     * @return array<int, array{text: string, tone: 'statement'|'question'}>
     */
    private function phrasesForHour(int $hour, bool $isLearner): array
    {
        if ($hour >= 5 && $hour < 12) {
            return [
                ['text' => 'Good Morning', 'tone' => 'statement'],
                ['text' => 'Ready to practice today', 'tone' => 'question'],
                ['text' => 'Hope you have a great morning', 'tone' => 'statement'],
            ];
        }

        if ($hour >= 12 && $hour < 17) {
            return [
                ['text' => 'Good Afternoon', 'tone' => 'statement'],
                ['text' => 'Hope your afternoon is going well', 'tone' => 'statement'],
                ['text' => 'Ready to continue your progress', 'tone' => 'question'],
            ];
        }

        if ($hour >= 17 && $hour < 22) {
            return [
                ['text' => 'Good Evening', 'tone' => 'statement'],
                ['text' => 'Unwinding with some practice', 'tone' => 'question'],
                ['text' => 'Great to see you this evening', 'tone' => 'statement'],
            ];
        }

        if ($isLearner) {
            return [
                ['text' => 'Burning the midnight oil', 'tone' => 'question'],
                ['text' => 'Late-night practice session', 'tone' => 'question'],
                ['text' => 'Working hard tonight', 'tone' => 'question'],
            ];
        }

        return [
            ['text' => 'Good Evening', 'tone' => 'statement'],
            ['text' => 'Great to see you this evening', 'tone' => 'statement'],
        ];
    }
}
