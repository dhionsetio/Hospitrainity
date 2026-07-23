<?php

namespace App\Models;

use App\Enums\AccountDisableReason;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\LegacyInstitutionState;
use App\Enums\PlatformRole;
use App\Enums\UserCapability;
use App\Enums\UserRole;
use App\Services\CurriculumProgressService;
use App\Services\Time\IanaTimeZone;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Stringable;

class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable;

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
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'occupation',
        'occupation_other',
        'prefix',
        'instansi',
        'email',
        'password',
        'timezone',
    ];

    /**
     * Get formatted full display name including prefix if present.
     */
    public function formattedName(): string
    {
        if ($this->first_name !== null && $this->first_name !== '') {
            return trim(implode(' ', array_filter([
                $this->prefix !== null && $this->prefix !== 'None' ? $this->prefix : null,
                $this->first_name,
                $this->middle_name,
                $this->last_name,
            ])));
        }

        return $this->name;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
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
            'disabled_at' => 'immutable_datetime',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'disabled_reason_code' => AccountDisableReason::class,
            'password' => 'hashed',
            'role' => UserRole::class,
            'legacy_institution_state' => LegacyInstitutionState::class,
            'ui_high_contrast' => 'boolean',
            'ui_no_audio' => 'boolean',
            'learning_streak_enabled' => 'boolean',
        ];
    }

    public static function canonicalEmail(mixed $email): string
    {
        if (! is_string($email) && ! ($email instanceof Stringable)) {
            return '';
        }

        return Str::lower(trim((string) $email));
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): string => static::canonicalEmail($value),
        );
    }

    protected function timezone(): Attribute
    {
        return Attribute::make(
            set: static fn (mixed $value): ?string => IanaTimeZone::nullable($value),
        );
    }

    public function isSuperAdmin(): bool
    {
        if ($this->role === UserRole::Superadmin) {
            return true;
        }

        return $this->exists
            && $this->platformRoleAssignments()
                ->where('role', PlatformRole::SystemAdmin->value)
                ->whereNull('revoked_at')
                ->exists();
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
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
        if ($this->isSuperAdmin() || $this->role === UserRole::Admin) {
            return true;
        }

        return $this->exists
            && $this->capabilityAssignments()
                ->where('capability', UserCapability::ContentAuthor->value)
                ->whereNull('revoked_at')
                ->exists();
    }

    public function completions(): HasMany
    {
        return $this->hasMany(Completion::class);
    }

    public function onboardingStates(): HasMany
    {
        return $this->hasMany(UserOnboardingState::class);
    }

    public function institutionMemberships(): HasMany
    {
        return $this->hasMany(InstitutionMembership::class);
    }

    public function createdCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'created_by_user_id');
    }

    public function createdCourseOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class, 'created_by_user_id');
    }

    public function platformRoleAssignments(): HasMany
    {
        return $this->hasMany(PlatformRoleAssignment::class);
    }

    public function capabilityAssignments(): HasMany
    {
        return $this->hasMany(UserCapabilityAssignment::class);
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(InstitutionJoinRequest::class);
    }

    public function privacyRequests(): HasMany
    {
        return $this->hasMany(DataSubjectRequest::class);
    }

    public function policyAcknowledgements(): HasMany
    {
        return $this->hasMany(PolicyAcknowledgement::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function mfaRecoveryCodes(): HasMany
    {
        return $this->hasMany(MfaRecoveryCode::class);
    }

    public function hasConfirmedTotp(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function hasStrongMfa(): bool
    {
        return $this->hasConfirmedTotp() || ($this->exists && $this->passkeys()->exists());
    }

    public function requiresMfa(): bool
    {
        if ($this->isSuperAdmin() || $this->isContentAdministrator() || $this->role !== UserRole::Learner) {
            return true;
        }

        if (! $this->exists) {
            return false;
        }

        return $this->institutionMemberships()
            ->where('status', InstitutionMembershipStatus::Active->value)
            ->whereHas('roleAssignments', fn ($query) => $query
                ->whereIn('role', [InstitutionRole::Instructor->value, InstitutionRole::InstitutionAdmin->value])
                ->whereNull('revoked_at'))
            ->exists();
    }

    public function hasInstitutionRole(Institution $institution, InstitutionRole ...$roles): bool
    {
        if ($roles === []) {
            return false;
        }

        return InstitutionRoleAssignment::query()
            ->whereIn('role', array_map(static fn (InstitutionRole $role): string => $role->value, $roles))
            ->whereNull('revoked_at')
            ->whereHas('membership', function ($query) use ($institution): void {
                $query->where('institution_id', $institution->getKey())
                    ->where('user_id', $this->getKey())
                    ->where('status', InstitutionMembershipStatus::Active->value);
            })
            ->exists();
    }

    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institution::class, 'institution_memberships')
            ->withPivot(['status', 'is_default', 'provenance', 'joined_at', 'revoked_at'])
            ->withTimestamps();
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
