<?php

namespace App\Exceptions;

use RuntimeException;

final class InvitationUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This invitation is unavailable.');
    }
}
