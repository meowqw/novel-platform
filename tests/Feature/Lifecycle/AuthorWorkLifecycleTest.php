<?php

use App\Models\User;
use App\Modules\Identity\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

function lifecycleDoc(string $text): array
{
    return [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => $text]],
            ],
        ],
    ];
}

function lifecyclePlainText(array $payload): string
{
    return $payload['content']['content'][0]['content'][0]['text'];
}

it('covers the full author work lifecycle through HTTP APIs', function (): void {
    Notification::fake();
    RateLimiter::clear(md5('auth-register'.'127.0.0.1'));

    $register = $this->postJson('/api/v1/auth/register', [
        'username' => 'lifecycle_author',
        'name' => 'Lifecycle Author',
        'email' => 'lifecycle.author@example.com',
        'password' => 'password12345',
        'password_confirmation' => 'password12345',
    ])->assertCreated();

    Notification::assertSentTo(
        User::query()->where('email', 'lifecycle.author@example.com')->first(),
        VerifyEmailNotification::class,
    );

    $token = $register->json('token');
    expect($token)->toBeString()->not->toBeEmpty();

    $work = $this->withToken($token)
        ->postJson('/api/v1/works', [
            'title' => 'Lifecycle Novel',
            'description' => 'E2E work',
        ])
        ->assertCreated()
        ->json('data');

    $chapter = $this->withToken($token)
        ->postJson('/api/v1/works/'.$work['id'].'/chapters', [
            'title' => 'Chapter One',
        ])
        ->assertCreated()
        ->json('data');

    $this->withToken($token)
        ->getJson('/api/v1/chapters/'.$chapter['id'].'/draft')
        ->assertOk()
        ->assertJsonPath('data.version', 1);

    $draftA = $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter['id'].'/draft', [
            'content' => lifecycleDoc('Text A'),
            'version' => 1,
        ])
        ->assertOk()
        ->json('data');

    expect($draftA['version'])->toBe(2)
        ->and(lifecyclePlainText($draftA))->toBe('Text A');

    $this->withToken($token)
        ->postJson('/api/v1/chapters/'.$chapter['id'].'/publish')
        ->assertOk()
        ->assertJsonPath('data.is_published', true)
        ->assertJsonPath('data.source_draft_version', 2);

    $this->withToken($token)
        ->putJson('/api/v1/works/'.$work['id'].'/publication', ['published' => true])
        ->assertOk()
        ->assertJsonPath('data.is_published', true);

    $publicA = $this->getJson('/api/v1/public/chapters/'.$chapter['id'])
        ->assertOk()
        ->json('data');

    expect(lifecyclePlainText($publicA))->toBe('Text A')
        ->and($publicA)->not->toHaveKey('source_draft_version')
        ->and($publicA)->not->toHaveKey('plain_text');

    $draftB = $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter['id'].'/draft', [
            'content' => lifecycleDoc('Text B'),
            'version' => 2,
        ])
        ->assertOk()
        ->json('data');

    expect(lifecyclePlainText($draftB))->toBe('Text B');

    $stillA = $this->getJson('/api/v1/public/chapters/'.$chapter['id'])
        ->assertOk()
        ->json('data');

    expect(lifecyclePlainText($stillA))->toBe('Text A');
    expect(json_encode($stillA, JSON_THROW_ON_ERROR))->not->toContain('Text B');

    $this->withToken($token)
        ->postJson('/api/v1/chapters/'.$chapter['id'].'/publish')
        ->assertOk()
        ->assertJsonPath('data.source_draft_version', 3);

    $publicB = $this->getJson('/api/v1/public/chapters/'.$chapter['id'])
        ->assertOk()
        ->json('data');

    expect(lifecyclePlainText($publicB))->toBe('Text B');

    $this->withToken($token)
        ->postJson('/api/v1/chapters/'.$chapter['id'].'/unpublish')
        ->assertOk()
        ->assertJsonPath('data.is_published', false);

    $this->getJson('/api/v1/public/chapters/'.$chapter['id'])->assertNotFound();
    $this->getJson('/api/v1/public/works/'.$work['id'])
        ->assertOk()
        ->assertJsonPath('data.chapters', []);

    $this->withToken($token)
        ->postJson('/api/v1/chapters/'.$chapter['id'].'/publish')
        ->assertOk();

    $this->getJson('/api/v1/public/chapters/'.$chapter['id'])->assertOk();

    $this->withToken($token)
        ->putJson('/api/v1/works/'.$work['id'].'/publication', ['published' => false])
        ->assertOk()
        ->assertJsonPath('data.is_published', false);

    $this->getJson('/api/v1/public/works/'.$work['id'])->assertNotFound();
    $this->getJson('/api/v1/public/chapters/'.$chapter['id'])->assertNotFound();

    RateLimiter::clear(md5('auth-register'.'127.0.0.1'));

    $intruder = $this->postJson('/api/v1/auth/register', [
        'username' => 'lifecycle_intruder',
        'name' => 'Intruder',
        'email' => 'lifecycle.intruder@example.com',
        'password' => 'password12345',
        'password_confirmation' => 'password12345',
    ])->assertCreated();

    $intruderToken = $intruder->json('token');
    auth()->forgetGuards();

    $this->withToken($intruderToken)
        ->patchJson('/api/v1/works/'.$work['id'], ['title' => 'Hijacked'])
        ->assertNotFound();

    $this->withToken($intruderToken)
        ->patchJson('/api/v1/works/'.$work['id'].'/chapters/'.$chapter['id'], [
            'title' => 'Hijacked chapter',
        ])
        ->assertNotFound();

    $this->withToken($intruderToken)
        ->putJson('/api/v1/chapters/'.$chapter['id'].'/draft', [
            'content' => lifecycleDoc('Stolen'),
            'version' => 3,
        ])
        ->assertNotFound();

    $this->withToken($intruderToken)
        ->postJson('/api/v1/chapters/'.$chapter['id'].'/publish')
        ->assertNotFound();

    $this->withToken($intruderToken)
        ->putJson('/api/v1/works/'.$work['id'].'/publication', ['published' => true])
        ->assertNotFound();
});
