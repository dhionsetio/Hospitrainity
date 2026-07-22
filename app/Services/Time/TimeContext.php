<?php

namespace App\Services\Time;

use App\Enums\LocalTimeDisambiguation;
use App\Models\CourseOffering;
use App\Models\Institution;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

final class TimeContext
{
    public function __construct(private readonly ClockInterface $clock) {}

    public function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }

    public function scheduleTimeZone(?Institution $institution = null, ?CourseOffering $offering = null): DateTimeZone
    {
        $institution = $this->resolveInstitution($institution, $offering);

        $offeringTimezone = $this->timeZoneAttribute($offering);
        if ($offeringTimezone !== null) {
            return new DateTimeZone($offeringTimezone);
        }
        $institutionTimezone = $this->timeZoneAttribute($institution);
        if ($institutionTimezone !== null) {
            return new DateTimeZone($institutionTimezone);
        }

        return new DateTimeZone('UTC');
    }

    public function displayTimeZone(
        ?User $user = null,
        ?Institution $institution = null,
        ?CourseOffering $offering = null,
    ): DateTimeZone {
        $institution = $this->resolveInstitution($institution, $offering);

        $userTimezone = $this->timeZoneAttribute($user);
        if ($userTimezone !== null) {
            return new DateTimeZone($userTimezone);
        }
        $offeringTimezone = $this->timeZoneAttribute($offering);
        if ($offeringTimezone !== null) {
            return new DateTimeZone($offeringTimezone);
        }
        $institutionTimezone = $this->timeZoneAttribute($institution);
        if ($institutionTimezone !== null) {
            return new DateTimeZone($institutionTimezone);
        }

        return new DateTimeZone('UTC');
    }

    public function nowForDisplay(
        ?User $user = null,
        ?Institution $institution = null,
        ?CourseOffering $offering = null,
    ): DateTimeImmutable {
        return $this->now()->setTimezone($this->displayTimeZone($user, $institution, $offering));
    }

    public function localWallTimeToUtc(
        string $localWallTime,
        string $timezone,
        LocalTimeDisambiguation $disambiguation = LocalTimeDisambiguation::Reject,
    ): DateTimeImmutable {
        $zone = new DateTimeZone(IanaTimeZone::required($timezone));
        $utc = new DateTimeZone('UTC');
        $naive = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $localWallTime, $utc);
        $errors = DateTimeImmutable::getLastErrors();

        if ($naive === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $naive->format('Y-m-d H:i:s') !== $localWallTime) {
            throw new InvalidArgumentException('Local wall time must use the exact format Y-m-d H:i:s.');
        }

        $offsets = $this->candidateOffsets($zone, $naive->getTimestamp());
        $candidates = [];
        foreach ($offsets as $offset) {
            $timestamp = $naive->getTimestamp() - $offset;
            $candidate = (new DateTimeImmutable('@'.$timestamp))->setTimezone($zone);
            if ($candidate->format('Y-m-d H:i:s') === $localWallTime) {
                $candidates[$timestamp] = $candidate->setTimezone($utc);
            }
        }

        ksort($candidates, SORT_NUMERIC);
        $matches = array_values($candidates);
        if ($matches === []) {
            throw new InvalidArgumentException('Local wall time does not exist in the selected timezone.');
        }

        if (count($matches) > 1) {
            return match ($disambiguation) {
                LocalTimeDisambiguation::Earlier => $matches[0],
                LocalTimeDisambiguation::Later => $matches[array_key_last($matches)],
                LocalTimeDisambiguation::Reject => throw new InvalidArgumentException(
                    'Local wall time occurs twice in the selected timezone; choose earlier or later explicitly.',
                ),
            };
        }

        return $matches[0];
    }

    /** @return list<int> */
    private function candidateOffsets(DateTimeZone $zone, int $around): array
    {
        $transitions = $zone->getTransitions($around - 172800, $around + 172800);

        return array_values(array_unique(array_map(
            static fn (array $transition): int => (int) $transition['offset'],
            $transitions,
        )));
    }

    private function resolveInstitution(
        ?Institution $institution,
        ?CourseOffering $offering,
    ): ?Institution {
        if ($offering === null) {
            return $institution;
        }
        if ($institution === null) {
            $institution = Institution::query()->find($offering->institution_id);
            if ($institution === null) {
                throw new InvalidArgumentException('Class Institution time context is unavailable.');
            }
        }
        if ($offering->institution_id !== $institution->getKey()) {
            throw new InvalidArgumentException('Class and Institution time contexts do not match.');
        }

        return $institution;
    }

    private function timeZoneAttribute(User|Institution|CourseOffering|null $model): ?string
    {
        if ($model === null) {
            return null;
        }

        return IanaTimeZone::nullable($model->getAttribute('timezone'));
    }
}
