<?php

namespace App\Modules\Library\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'max:500'],
            'order.*' => ['required', 'uuid', 'distinct'],
            'volume_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
