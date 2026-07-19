<?php

namespace App\Models;

use App\Enums\CurriculumImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CurriculumImport extends Model
{
    protected $fillable = [
        'curriculum_draft_id', 'base_package_id', 'status', 'revision', 'source_original_name',
        'source_storage_path', 'source_sha256', 'source_bytes', 'detected_mime',
        'declared_purpose', 'compiled_package_path', 'evidence_path', 'compiler_report',
        'inventory_report', 'diff_report', 'error_report', 'created_by', 'accepted_by',
        'processed_at', 'accepted_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $import): void {
            $import->public_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => CurriculumImportStatus::class,
            'revision' => 'integer',
            'source_bytes' => 'integer',
            'compiler_report' => 'array',
            'inventory_report' => 'array',
            'diff_report' => 'array',
            'error_report' => 'array',
            'processed_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(CurriculumDraft::class, 'curriculum_draft_id');
    }

    public function basePackage(): BelongsTo
    {
        return $this->belongsTo(CurriculumPackage::class, 'base_package_id');
    }
}
