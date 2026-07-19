<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumImportRun extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'curriculum_package_id',
        'action',
        'status',
        'source_tree_sha256',
        'before_database_sha256',
        'after_database_sha256',
        'standalone_sha256',
        'rollback_path',
        'rollback_sha256',
        'report_path',
        'report',
    ];

    protected function casts(): array
    {
        return ['report' => 'array'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CurriculumPackage::class, 'curriculum_package_id');
    }
}
