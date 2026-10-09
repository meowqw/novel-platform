<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Resources\PersonalAccessTokenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TokenController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tokens = $request->user()
            ->tokens()
            ->orderByDesc('created_at')
            ->get();

        return PersonalAccessTokenResource::collection($tokens);
    }

    public function destroy(Request $request, string $id): JsonResponse|Response
    {
        $deleted = $request->user()
            ->tokens()
            ->whereKey($id)
            ->delete();

        if ($deleted === 0) {
            return response()->json([
                'message' => __('Not Found'),
            ], 404);
        }

        return response()->noContent();
    }
}
