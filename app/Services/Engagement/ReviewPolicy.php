<?php

namespace App\Services\Engagement;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class ReviewPolicy
{
    /** @var list<int> */
    private readonly array $intervalDays;

    /**
     * @param  list<mixed>  $intervalDays
     */
    public function __construct(
        private readonly string $version,
        array $intervalDays,
    ) {
        if ($this->version === '' || $intervalDays === []) {
            throw new InvalidArgumentException('A review policy version and at least one interval are required.');
        }

        foreach ($intervalDays as $days) {
            if (! is_int($days) || $days < 1) {
                throw new InvalidArgumentException('Review intervals must be positive whole days.');
            }
        }

        $this->intervalDays = $intervalDays;
    }

    public static function configured(): self
    {
        /** @var mixed $version */
        $version = config('learning_engagement.review_policy.version');
        /** @var mixed $intervals */
        $intervals = config('learning_engagement.review_policy.interval_days');

        if (! is_string($version) || ! is_array($intervals)) {
            throw new InvalidArgumentException('The configured review policy is invalid.');
        }

        return new self($version, array_values($intervals));
    }

    /**
     * A null outcome represents an open response without an objective score.
     * It keeps the current interval. A failed objective attempt returns to the
     * first interval. A successful review advances by one bounded step.
     *
     * @return array{version: string, step: int, due_at: CarbonImmutable}
     */
    public function next(
        CarbonImmutable $completedAt,
        int $currentStep,
        ?bool $successful,
        bool $hasPriorReview,
    ): array {
        $lastStep = array_key_last($this->intervalDays);
        $step = max(0, min($currentStep, $lastStep));

        if (! $hasPriorReview || $successful === false) {
            $step = 0;
        } elseif ($successful === true) {
            $step = min($step + 1, $lastStep);
        }

        return [
            'version' => $this->version,
            'step' => $step,
            'due_at' => $completedAt->addDays($this->intervalDays[$step]),
        ];
    }

    /** @return list<int> */
    public function intervals(): array
    {
        return $this->intervalDays;
    }
}
