<?php

namespace App\Modules\Library\Http\Requests;

use App\Modules\Library\Models\Work;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkRequest extends FormRequest
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
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'slug' => ['sometimes', 'nullable', 'string', 'min:1', 'max:220'],
            'status' => ['sometimes', 'string', Rule::in(Work::STATUSES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('title') && is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }
    }
}
