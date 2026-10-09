<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('Email already verified.'),
            ]);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'message' => __('Verification link sent.'),
        ]);
    }

    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::query()->find($id);

        if ($user === null || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json([
                'message' => __('Invalid verification link.'),
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('Email already verified.'),
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => __('Email verified.'),
        ]);
    }
}
