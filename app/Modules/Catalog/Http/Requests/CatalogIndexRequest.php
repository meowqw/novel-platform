<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Library\Models\Work;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogIndexRequest extends FormRequest
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in(Work::STATUSES)],
            'sort' => ['sometimes', 'string', Rule::in(['recent', 'published', 'title'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('q') || ! is_string($this->input('q'))) {
            return;
        }

        $trimmed = trim($this->input('q'));

        $this->merge([
            'q' => $trimmed === '' ? null : $trimmed,
        ]);
    }

    public function searchQuery(): ?string
    {
        $q = $this->validated('q');

        return is_string($q) && $q !== '' ? $q : null;
    }

    public function sort(): string
    {
        return (string) ($this->validated('sort') ?? 'recent');
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 20);
    }
}
