<?php

use App\Modules\Editor\Support\DocumentValidator;
use Illuminate\Validation\ValidationException;

it('accepts an empty document and nested allow-listed nodes', function (): void {
    $validator = new DocumentValidator;

    expect($validator->validate(config('editor.empty_document')))->toBeArray();

    $doc = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'heading',
                'attrs' => ['level' => 1],
                'content' => [
                    ['type' => 'text', 'text' => 'Заголовок', 'marks' => [['type' => 'bold']]],
                ],
            ],
            [
                'type' => 'paragraph',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'ссылка',
                        'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com']]],
                    ],
                    ['type' => 'hardBreak'],
                    ['type' => 'text', 'text' => 'хвост'],
                ],
            ],
            [
                'type' => 'bulletList',
                'content' => [
                    [
                        'type' => 'listItem',
                        'content' => [
                            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'item']]],
                        ],
                    ],
                ],
            ],
            ['type' => 'horizontalRule'],
        ],
    ];

    expect($validator->validate($doc)['type'])->toBe('doc');
});

it('rejects unsafe links, unknown nodes and non-doc roots', function (): void {
    $validator = new DocumentValidator;

    expect(fn () => $validator->validate(['type' => 'paragraph', 'content' => []]))
        ->toThrow(ValidationException::class);

    expect(fn () => $validator->validate([
        'type' => 'doc',
        'content' => [['type' => 'image']],
    ]))->toThrow(ValidationException::class);

    expect(fn () => $validator->validate([
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'x',
                        'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]],
                    ],
                ],
            ],
        ],
    ]))->toThrow(ValidationException::class);
});
