<?php

namespace App\Modules\Media\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $media_id
 * @property string $work_id
 * @property string $chapter_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PublishedMediaReference extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'media_id',
        'work_id',
        'chapter_id',
    ];

    /** @return BelongsTo<MediaAsset, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_id');
    }
}
