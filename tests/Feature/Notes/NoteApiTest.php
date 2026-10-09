<?php

use App\Modules\Example\Models\Note;

it('creates a note', function (): void {
    $response = $this->postJson('/api/v1/notes', [
        'title' => 'Foundation note',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Foundation note')
        ->assertJsonStructure([
            'data' => ['id', 'title', 'created_at', 'updated_at'],
        ]);

    $this->assertDatabaseHas('notes', [
        'title' => 'Foundation note',
    ]);
});

it('rejects an invalid note payload', function (): void {
    $response = $this->postJson('/api/v1/notes', [
        'title' => '',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('returns a note by id', function (): void {
    $note = Note::query()->create([
        'title' => 'Readable note',
    ]);

    $response = $this->getJson('/api/v1/notes/'.$note->id);

    $response->assertOk()
        ->assertJsonPath('data.id', $note->id)
        ->assertJsonPath('data.title', 'Readable note');
});

it('returns not found for a missing note', function (): void {
    $this->getJson('/api/v1/notes/999999')
        ->assertNotFound();
});
