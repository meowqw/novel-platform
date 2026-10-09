<?php

namespace App\Modules\Example\Actions;

use App\Modules\Example\Models\Note;

class CreateNote
{
    public function handle(string $title): Note
    {
        return Note::query()->create([
            'title' => $title,
        ]);
    }
}
