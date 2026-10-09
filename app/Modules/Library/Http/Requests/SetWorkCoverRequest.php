<?php

namespace App\Modules\Library\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetWorkCoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'media_id' => ['required', 'uuid'],
        ];
    }
}
