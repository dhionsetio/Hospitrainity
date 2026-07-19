<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingMediaDeletion extends Model
{
    protected $fillable = [
        'disk',
        'path',
        'attempts',
        'last_error',
    ];
}
