<?php

namespace App\Modules\Library\Models;

use App\Models\User;
use Database\Factories\Library\WorkFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $author_id
 * @property string $title
 * @property string|null $description
 * @property string $slug
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Work extends Model
{
    /** @use HasFactory<WorkFactory> */
    use HasFactory, HasUuids;

    public const STATUSES = ['ongoing', 'completed', 'hiatus'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'author_id',
        'title',
        'description',
        'slug',
        'status',
    ];

    protected static function newFactory(): WorkFactory
    {
        return WorkFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasMany<Volume, $this> */
    public function volumes(): HasMany
    {
        return $this->hasMany(Volume::class)->orderBy('position');
    }

    /** @return HasMany<Chapter, $this> */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('position');
    }
}
