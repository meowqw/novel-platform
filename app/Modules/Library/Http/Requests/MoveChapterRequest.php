<?php

namespace App\Modules\Library\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveChapterRequest extends FormRequest
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
            'volume_id' => ['present', 'nullable', 'uuid'],
            'position' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
