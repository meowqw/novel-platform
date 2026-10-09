<?php

namespace App\Modules\Library\Policies;

use App\Models\User;
use App\Modules\Library\Models\Work;

class WorkPolicy
{
    public function view(User $user, Work $work): bool
    {
        return $work->author_id === $user->id;
    }

    public function update(User $user, Work $work): bool
    {
        return $work->author_id === $user->id;
    }

    public function delete(User $user, Work $work): bool
    {
        return $work->author_id === $user->id;
    }
}
