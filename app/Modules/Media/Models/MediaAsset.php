<?php

namespace App\Modules\Media\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $owner_id
 * @property string $kind
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $byte_size
 * @property int $width
 * @property int $height
 * @property string $checksum_sha256
 * @property string|null $thumb_path
 * @property int|null $thumb_byte_size
 * @property int|null $thumb_width
 * @property int|null $thumb_height
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaAsset extends Model
{
    use HasUuids;

    public const KIND_COVER = 'cover';

    public const KIND_CHAPTER_IMAGE = 'chapter_image';

    public const KINDS = [self::KIND_COVER, self::KIND_CHAPTER_IMAGE];

    public const VARIANT_ORIGINAL = 'original';

    public const VARIANT_THUMB = 'thumb';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'kind',
        'disk',
        'path',
        'mime_type',
        'byte_size',
        'width',
        'height',
        'checksum_sha256',
        'thumb_path',
        'thumb_byte_size',
        'thumb_width',
        'thumb_height',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'thumb_byte_size' => 'integer',
            'thumb_width' => 'integer',
            'thumb_height' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function hasThumb(): bool
    {
        return is_string($this->thumb_path) && $this->thumb_path !== '';
    }
}
