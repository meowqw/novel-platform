<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\RevokeUserSessions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class ChangePassword
{
    public function __construct(
        private readonly RevokeUserSessions $revokeUserSessions,
    ) {}

    public function handle(User $user, string $currentPassword, string $newPassword, ?PersonalAccessToken $currentToken = null): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('auth.password')],
            ]);
        }

        $user->forceFill([
            'password' => $newPassword,
        ])->save();

        $query = $user->tokens();

        if ($currentToken !== null) {
            $query->whereKeyNot($currentToken->getKey());
        }

        $query->delete();

        // Cookie/session rows (future SPA Sanctum); distinct from API PAT revocation.
        $this->revokeUserSessions->handle($user);
    }
}
