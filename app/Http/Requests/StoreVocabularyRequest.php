<?php

namespace App\Http\Requests;

class StoreVocabularyRequest extends VocabularyRequest
{
    protected function isUpdate(): bool
    {
        return false;
    }
}
