<?php

namespace App\Exceptions;

use RuntimeException;

class CurriculumDraftConflictException extends RuntimeException
{
    public function __construct(public readonly int $expectedRevision, public readonly int $actualRevision)
    {
        parent::__construct("The draft changed in another session (expected revision {$expectedRevision}; current revision {$actualRevision}). Reload before saving again.");
    }
}
