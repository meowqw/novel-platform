<?php

namespace App\Modules\Editor\Models;

use App\Models\User;
use App\Modules\Library\Models\Chapter;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $chapter_id
 * @property array<string, mixed> $content_json
 * @property string $plain_text
 * @property int $source_version
 * @property string $created_by
 * @property string|null $label
 * @property string|null $idempotency_key
 * @property Carbon|null $created_at
 */
class ChapterRevision extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'chapter_id',
        'content_json',
        'plain_text',
        'source_version',
        'created_by',
        'label',
        'idempotency_key',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_json' => 'array',
            'source_version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Chapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'chapter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
