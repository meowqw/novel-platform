<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Actions\UpdateProfile;
use App\Modules\Identity\Http\Requests\UpdateProfileRequest;
use App\Modules\Identity\Http\Resources\PrivateUserResource;
use App\Modules\Identity\Http\Resources\PublicUserResource;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function show(string $username): PublicUserResource|JsonResponse
    {
        $user = User::query()
            ->whereRaw('LOWER(username) = ?', [strtolower($username)])
            ->first();

        if ($user === null) {
            return response()->json([
                'message' => __('Not Found'),
            ], 404);
        }

        return new PublicUserResource($user);
    }

    public function update(UpdateProfileRequest $request, UpdateProfile $updateProfile): PrivateUserResource
    {
        $user = $updateProfile->handle(
            $request->user(),
            $request->safe()->only(['username', 'name', 'bio']),
        );

        return new PrivateUserResource($user);
    }
}
