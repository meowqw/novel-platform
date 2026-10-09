<?php

namespace App\Modules\Library\Models;

use Database\Factories\Library\ChapterFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $work_id
 * @property string|null $volume_id
 * @property string $title
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Chapter extends Model
{
    /** @use HasFactory<ChapterFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'work_id',
        'volume_id',
        'title',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    protected static function newFactory(): ChapterFactory
    {
        return ChapterFactory::new();
    }

    /** @return BelongsTo<Work, $this> */
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    /** @return BelongsTo<Volume, $this> */
    public function volume(): BelongsTo
    {
        return $this->belongsTo(Volume::class);
    }
}
