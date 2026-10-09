<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\ChangeEmail;
use App\Modules\Identity\Actions\ChangePassword;
use App\Modules\Identity\Actions\ResetPassword;
use App\Modules\Identity\Http\Requests\ChangeEmailRequest;
use App\Modules\Identity\Http\Requests\ChangePasswordRequest;
use App\Modules\Identity\Http\Requests\ForgotPasswordRequest;
use App\Modules\Identity\Http\Requests\ResetPasswordRequest;
use App\Modules\Identity\Http\Resources\PrivateUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;

class PasswordResetController extends Controller
{
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink([
            'email' => $request->validated('email'),
        ]);

        return response()->json([
            'message' => __('If that email address is registered, a reset link has been sent.'),
        ]);
    }

    public function reset(ResetPasswordRequest $request, ResetPassword $resetPassword): JsonResponse
    {
        $resetPassword->handle([
            'email' => $request->validated('email'),
            'token' => $request->validated('token'),
            'password' => $request->validated('password'),
            'password_confirmation' => $request->input('password_confirmation'),
        ]);

        return response()->json([
            'message' => __('Password has been reset.'),
        ]);
    }

    public function updatePassword(ChangePasswordRequest $request, ChangePassword $changePassword): JsonResponse
    {
        $bearer = $request->bearerToken();
        $current = is_string($bearer) && $bearer !== ''
            ? PersonalAccessToken::findToken($bearer)
            : null;

        $changePassword->handle(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
            $current instanceof PersonalAccessToken ? $current : null,
        );

        return response()->json([
            'message' => __('Password updated.'),
        ]);
    }

    public function updateEmail(ChangeEmailRequest $request, ChangeEmail $changeEmail): PrivateUserResource
    {
        $user = $changeEmail->handle(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('email'),
        );

        return new PrivateUserResource($user);
    }
}
