<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumPackage extends Model
{
    protected $fillable = [
        'package_name',
        'content_version',
        'schema_version',
        'namespace_uuid',
        'lifecycle_status',
        'source_path',
        'source_tree_sha256',
        'source_file_count',
        'source_byte_count',
        'counts',
        'projection_meta',
        'laravel_projection_sha256',
        'standalone_sha256',
        'is_active',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'counts' => 'array',
            'projection_meta' => 'array',
            'is_active' => 'boolean',
            'imported_at' => 'datetime',
        ];
    }

    public function sourceFiles(): HasMany
    {
        return $this->hasMany(CurriculumSourceFile::class);
    }

    public function entities(): HasMany
    {
        return $this->hasMany(CurriculumEntity::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(CurriculumLink::class);
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }
}
