<?php

namespace App\Modules\Editor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestoreRevisionRequest extends FormRequest
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
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
