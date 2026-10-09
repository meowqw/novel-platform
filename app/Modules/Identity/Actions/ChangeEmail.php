<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangeEmail
{
    public function handle(User $user, string $currentPassword, string $newEmail): User
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('auth.password')],
            ]);
        }

        $user->forceFill([
            'email' => strtolower($newEmail),
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();

        return $user->refresh();
    }
}
