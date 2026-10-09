<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\RevokeUserSessions;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResetPassword
{
    public function __construct(
        private readonly RevokeUserSessions $revokeUserSessions,
    ) {}

    /**
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     */
    public function handle(array $credentials): void
    {
        $status = Password::reset(
            [
                'email' => strtolower($credentials['email']),
                'password' => $credentials['password'],
                'password_confirmation' => $credentials['password_confirmation'] ?? $credentials['password'],
                'token' => $credentials['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // Password reset revokes all API tokens.
                $user->tokens()->delete();

                // Also clear any cookie/session rows for this user.
                $this->revokeUserSessions->handle($user);

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }
}
