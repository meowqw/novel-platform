<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;

class UpdateProfile
{
    /**
     * @param  array{username?: string, name?: string, bio?: string|null}  $data
     */
    public function handle(User $user, array $data): User
    {
        if (array_key_exists('username', $data)) {
            $user->username = strtolower($data['username']);
        }

        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }

        if (array_key_exists('bio', $data)) {
            $user->bio = $data['bio'];
        }

        $user->save();

        return $user->refresh();
    }
}
