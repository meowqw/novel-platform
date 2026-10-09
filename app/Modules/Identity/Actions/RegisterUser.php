<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\IssueApiToken;
use Illuminate\Auth\Events\Registered;
use Laravel\Sanctum\NewAccessToken;

class RegisterUser
{
    public function __construct(
        private readonly IssueApiToken $issueApiToken,
    ) {}

    /**
     * @param  array{username: string, name: string, email: string, password: string}  $data
     * @return array{user: User, token: NewAccessToken}
     */
    public function handle(array $data): array
    {
        $user = User::query()->create([
            'username' => strtolower($data['username']),
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
        ]);

        event(new Registered($user));

        $token = $this->issueApiToken->handle($user);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
