<?php

namespace App\Models;

use App\Enums\CurriculumDraftStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CurriculumDraft extends Model
{
    protected $fillable = [
        'base_package_id', 'published_package_id', 'package_name', 'content_version',
        'schema_version', 'namespace_uuid', 'title', 'status', 'revision',
        'validation_report', 'diff_report', 'publication_run_id', 'created_by',
        'updated_by', 'validated_by', 'validated_at', 'submitted_by', 'submitted_at',
        'approved_by', 'approved_at', 'published_by', 'published_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $draft): void {
            $draft->public_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => CurriculumDraftStatus::class,
            'revision' => 'integer',
            'validation_report' => 'array',
            'diff_report' => 'array',
            'validated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function basePackage(): BelongsTo
    {
        return $this->belongsTo(CurriculumPackage::class, 'base_package_id');
    }

    public function publishedPackage(): BelongsTo
    {
        return $this->belongsTo(CurriculumPackage::class, 'published_package_id');
    }

    public function entities(): HasMany
    {
        return $this->hasMany(CurriculumDraftEntity::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(CurriculumDraftBlock::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CurriculumDraftEvent::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(CurriculumImport::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(CurriculumAsset::class);
    }
}
