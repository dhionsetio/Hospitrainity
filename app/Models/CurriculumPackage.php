<?php

namespace App\Models;

use App\Services\Curriculum\CurriculumReleaseGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use RuntimeException;

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

    public function release(): HasOne
    {
        return $this->hasOne(CurriculumRelease::class);
    }

    public static function active(): ?self
    {
        $packages = static::query()->with('release')->where('is_active', true)->limit(2)->get();
        if ($packages->count() > 1) {
            throw new RuntimeException('Multiple curriculum packages are marked active; delivery is fail-closed.');
        }

        $package = $packages->first();
        if ($package !== null) {
            app(CurriculumReleaseGuard::class)->assertDeliverable($package);
        }

        return $package;
    }
}
