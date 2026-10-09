<?php

namespace App\Modules\Identity\Support;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

class IssueApiToken
{
    public function handle(User $user, ?string $name = null): NewAccessToken
    {
        $expiresAt = now()->addMinutes((int) config('identity.token_expiration_minutes'));

        return $user->createToken(
            $name ?? (string) config('identity.token_name'),
            ['*'],
            $expiresAt,
        );
    }
}
