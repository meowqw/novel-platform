<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\AuthenticateUser;
use App\Modules\Identity\Actions\RegisterUser;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Resources\PrivateUserResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        try {
            $result = $registerUser->handle($request->safe()->only([
                'username',
                'name',
                'email',
                'password',
            ]));
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw ValidationException::withMessages([
                    'email' => [__('validation.unique', ['attribute' => 'email'])],
                ]);
            }

            throw $exception;
        }

        return (new PrivateUserResource($result['user']))
            ->additional([
                'token' => $result['token']->plainTextToken,
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request, AuthenticateUser $authenticateUser): JsonResponse
    {
        $result = $authenticateUser->handle(
            $request->validated('email'),
            $request->validated('password'),
        );

        return (new PrivateUserResource($result['user']))
            ->additional([
                'token' => $result['token']->plainTextToken,
            ])
            ->response();
    }

    public function me(Request $request): PrivateUserResource
    {
        return new PrivateUserResource($request->user());
    }

    public function logout(Request $request): Response
    {
        $bearer = $request->bearerToken();

        if (is_string($bearer) && $bearer !== '') {
            PersonalAccessToken::findToken($bearer)?->delete();
        }

        return response()->noContent();
    }

    public function logoutAll(Request $request): Response
    {
        $request->user()?->tokens()->delete();

        return response()->noContent();
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return $sqlState === '23505';
    }
}
