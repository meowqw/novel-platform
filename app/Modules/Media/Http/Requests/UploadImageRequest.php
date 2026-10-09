<?php

namespace App\Modules\Media\Http\Requests;

use App\Modules\Media\Models\MediaAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadImageRequest extends FormRequest
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
        $maxKilobytes = (int) ceil(((int) config('media.max_upload_bytes')) / 1024);

        return [
            'kind' => ['required', 'string', Rule::in(MediaAsset::KINDS)],
            'file' => [
                'required',
                'file',
                'max:'.$maxKilobytes,
                'mimetypes:image/jpeg,image/png,image/webp',
            ],
        ];
    }
}
