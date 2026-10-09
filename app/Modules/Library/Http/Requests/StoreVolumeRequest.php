<?php

namespace App\Modules\Library\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVolumeRequest extends FormRequest
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
            'title' => ['required', 'string', 'min:1', 'max:200'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('title') && is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }
    }
}
