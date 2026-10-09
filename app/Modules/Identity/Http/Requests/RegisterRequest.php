<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Support\ReservedUsernames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'username' => [
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
                Rule::unique('users', 'username'),
            ],
            'name' => ['required', 'string', 'min:1', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique('users', 'email')],
            'password' => [
                'required',
                'confirmed',
                'max:'.(int) config('identity.password_max'),
                Password::min((int) config('identity.password_min')),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('username') && is_string($this->input('username'))) {
            $this->merge(['username' => strtolower($this->input('username'))]);
        }

        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge(['email' => strtolower($this->input('email'))]);
        }
    }
}
