<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\IssueApiToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

class AuthenticateUser
{
    public function __construct(
        private readonly IssueApiToken $issueApiToken,
    ) {}

    /**
     * @return array{user: User, token: NewAccessToken}
     */
    public function handle(string $email, string $password): array
    {
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $this->issueApiToken->handle($user);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
