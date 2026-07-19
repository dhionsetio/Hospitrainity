<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumSourceFile extends Model
{
    public $timestamps = false;

    protected $fillable = ['curriculum_package_id', 'path', 'sha256', 'bytes'];

    public function package(): BelongsTo
    {
        return $this->belongsTo(CurriculumPackage::class, 'curriculum_package_id');
    }
}
