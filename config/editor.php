<?php

return [

    'max_json_bytes' => (int) env('EDITOR_MAX_JSON_BYTES', 1_048_576),

    'max_plain_text_chars' => (int) env('EDITOR_MAX_PLAIN_TEXT_CHARS', 200_000),

    'max_nodes' => (int) env('EDITOR_MAX_NODES', 20_000),

    'max_depth' => (int) env('EDITOR_MAX_DEPTH', 32),

    'allowed_nodes' => [
        'doc',
        'paragraph',
        'text',
        'heading',
        'bulletList',
        'orderedList',
        'listItem',
        'blockquote',
        'hardBreak',
        'horizontalRule',
    ],

    'allowed_marks' => [
        'bold',
        'italic',
        'strike',
        'code',
        'link',
    ],

    'heading_levels' => [1, 2, 3],

    'empty_document' => [
        'type' => 'doc',
        'content' => [],
    ],

];
