<?php

namespace App\Modules\Identity\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 *
 * @property User $resource
 */
class PublicUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'username' => $this->resource->username,
            'name' => $this->resource->name,
            'bio' => $this->resource->bio,
            'avatar_url' => $this->resource->avatar_path,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
