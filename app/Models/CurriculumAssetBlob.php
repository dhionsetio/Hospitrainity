<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurriculumAssetBlob extends Model
{
    protected $fillable = ['sha256', 'storage_path', 'detected_mime', 'extension', 'bytes'];

    protected function casts(): array
    {
        return ['bytes' => 'integer'];
    }
}
