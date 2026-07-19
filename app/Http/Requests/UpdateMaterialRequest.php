<?php

namespace App\Http\Requests;

class UpdateMaterialRequest extends MaterialRequest
{
    protected function isUpdate(): bool
    {
        return true;
    }
}
