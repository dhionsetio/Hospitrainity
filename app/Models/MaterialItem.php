<?php

namespace App\Models;

use App\Models\Concerns\Completable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialItem extends Model
{
    use Completable;
    use HasFactory;

    protected $fillable = ['material_id', 'title', 'description', 'url', 'audio_url', 'order'];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
