<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Services\CurriculumProgressService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * How long a computed overall-progress value stays cached.
     */
    private const PROGRESS_CACHE_TTL_HOURS = 6;

    private const PROGRESS_CACHE_GENERATION_KEY = 'progress:curriculum-generation';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'instansi',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public static function canonicalEmail(mixed $email): string
    {
        return Str::lower(trim((string) $email));
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): string => static::canonicalEmail($value),
        );
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::Superadmin;
    }

    public function isLearner(): bool
    {
        return $this->role === UserRole::Learner;
    }

    public function isSupervisor(): bool
    {
        return $this->role === UserRole::Supervisor;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isContentAdministrator(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Superadmin], true);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(Completion::class);
    }

    /**
     * Overall completion percentage (0-100) across all published modules.
     *
     * The supervisor dashboard calls this once per learner, so the (bounded but
     * non-trivial) computation is cached per user under "progress:user:{id}" and
     * invalidated in ProgressController::store() whenever the learner records new
     * progress. See computeOverallProgress() for the query-flat implementation.
     */
    public function getOverallProgress(): int
    {
        return Cache::remember(
            self::progressCacheKey($this->getKey()),
            now()->addHours(self::PROGRESS_CACHE_TTL_HOURS),
            fn (): int => $this->computeOverallProgress(),
        );
    }

    /**
     * Compute overall progress from eager-loaded data. Published modules and
     * every lesson's progress relations are loaded up front, and the user's
     * completions are fetched in a SINGLE query for all lessons combined, so the
     * query count does not grow with the number of lessons.
     *
     * The averaging is intentionally identical to the previous implementation:
     * each module is the (rounded) average of its lessons, and the overall score
     * is the (rounded) average of those per-module values.
     */
    protected function computeOverallProgress(): int
    {
        return app(CurriculumProgressService::class)->overallForUser($this);
    }

    /**
     * Cache key holding this user's computed overall-progress percentage.
     */
    public static function progressCacheKey(int|string $userId): string
    {
        return "progress:user:{$userId}:v".static::progressCacheGeneration();
    }

    /**
     * Invalidate every learner's cached aggregate after the published curriculum
     * or its denominator changes. A single generation bump replaces the former
     * one-cache-delete-per-learner loop, so administrative writes remain bounded
     * as learner count grows. Old generation entries expire under the normal TTL.
     */
    public static function forgetAllProgressCaches(): void
    {
        Cache::rememberForever(self::PROGRESS_CACHE_GENERATION_KEY, static fn (): int => 1);
        Cache::increment(self::PROGRESS_CACHE_GENERATION_KEY);
    }

    /**
     * Drop this user's cached overall-progress value (call after their progress
     * changes so the next read recomputes).
     */
    public function forgetProgressCache(): void
    {
        Cache::forget(self::progressCacheKey($this->getKey()));
    }

    private static function progressCacheGeneration(): int
    {
        $generation = Cache::rememberForever(
            self::PROGRESS_CACHE_GENERATION_KEY,
            static fn (): int => 1,
        );

        return max(1, (int) $generation);
    }
}
