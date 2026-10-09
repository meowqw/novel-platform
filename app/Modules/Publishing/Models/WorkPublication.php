<?php

namespace App\Modules\Publishing\Models;

use App\Modules\Library\Models\Work;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $work_id
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property Carbon|null $unpublished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WorkPublication extends Model
{
    protected $primaryKey = 'work_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'work_id',
        'is_published',
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
            'published_at' => 'datetime',
            'unpublished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Work, $this> */
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class, 'work_id');
    }
}
