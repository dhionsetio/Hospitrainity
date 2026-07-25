<?php

namespace App\Enums;

enum LocalTimeDisambiguation: string
{
    case Reject = 'reject';
    case Earlier = 'earlier';
    case Later = 'later';
}
