<?php

namespace App\Modules\Example\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Note extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
    ];
}
