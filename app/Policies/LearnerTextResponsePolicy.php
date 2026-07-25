<?php

namespace App\Policies;

use App\Models\LearnerTextResponse;
use App\Models\User;

class LearnerTextResponsePolicy
{
    public function view(User $user, LearnerTextResponse $response): bool
    {
        return (int) $response->user_id === (int) $user->getKey();
    }

    public function update(User $user, LearnerTextResponse $response): bool
    {
        return $this->view($user, $response) && $response->state === 'draft';
    }
}
