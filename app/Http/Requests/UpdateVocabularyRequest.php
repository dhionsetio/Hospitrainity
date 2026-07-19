<?php

namespace App\Http\Requests;

class UpdateVocabularyRequest extends VocabularyRequest
{
    protected function isUpdate(): bool
    {
        return true;
    }
}
