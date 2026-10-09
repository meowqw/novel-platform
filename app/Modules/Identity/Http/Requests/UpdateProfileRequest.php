<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Support\ReservedUsernames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
        $userId = $this->user()?->id;

        return [
            'username' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-zA-Z0-9_]+$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && ReservedUsernames::contains($value)) {
                        $fail(__('validation.unique', ['attribute' => $attribute]));
                    }
                },
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'name' => ['sometimes', 'required', 'string', 'min:1', 'max:80'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('username') && is_string($this->input('username'))) {
            $this->merge(['username' => strtolower($this->input('username'))]);
        }
    }
}
