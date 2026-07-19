<?php

namespace App\Enums;

enum CurriculumImportStatus: string
{
    case Rejected = 'rejected';
    case Quarantined = 'quarantined';
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case Accepted = 'accepted';
    case Failed = 'failed';
}
