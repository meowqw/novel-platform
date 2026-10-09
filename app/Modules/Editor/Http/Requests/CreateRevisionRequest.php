<?php

namespace App\Modules\Editor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRevisionRequest extends FormRequest
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
            'label' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
