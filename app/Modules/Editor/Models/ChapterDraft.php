<?php

namespace App\Modules\Editor\Models;

use App\Modules\Library\Models\Chapter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $chapter_id
 * @property array<string, mixed> $content_json
 * @property string $plain_text
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ChapterDraft extends Model
{
    protected $primaryKey = 'chapter_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'chapter_id',
        'content_json',
        'plain_text',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_json' => 'array',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Chapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'chapter_id');
    }
}
