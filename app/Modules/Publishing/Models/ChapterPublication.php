<?php

namespace App\Modules\Publishing\Models;

use App\Modules\Library\Models\Chapter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $chapter_id
 * @property bool $is_published
 * @property array<string, mixed>|null $content_json
 * @property string|null $plain_text
 * @property int|null $source_draft_version
 * @property Carbon|null $published_at
 * @property Carbon|null $unpublished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ChapterPublication extends Model
{
    protected $primaryKey = 'chapter_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'chapter_id',
        'is_published',
        'content_json',
        'plain_text',
        'source_draft_version',
        'published_at',
        'unpublished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'content_json' => 'array',
            'source_draft_version' => 'integer',
            'published_at' => 'datetime',
            'unpublished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Chapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'chapter_id');
    }
}
