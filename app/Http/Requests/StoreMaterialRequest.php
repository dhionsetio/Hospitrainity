<?php

namespace App\Http\Requests;

class StoreMaterialRequest extends MaterialRequest
{
    protected function isUpdate(): bool
    {
        return false;
    }
}
