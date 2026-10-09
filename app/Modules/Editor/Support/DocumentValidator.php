<?php

namespace App\Modules\Editor\Support;

use Illuminate\Validation\ValidationException;

class DocumentValidator
{
    private int $nodeCount = 0;

    /**
     * @return array<string, mixed>
     */
    public function validate(mixed $document): array
    {
        $encoded = json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        if (strlen($encoded) > (int) config('editor.max_json_bytes')) {
            throw ValidationException::withMessages([
                'content' => ['Document exceeds maximum JSON size.'],
            ]);
        }

        if (! is_array($document)) {
            throw ValidationException::withMessages([
                'content' => ['Document must be a JSON object.'],
            ]);
        }

        $this->nodeCount = 0;
        $this->assertNode($document, 0, requiredType: 'doc');

        return $document;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function assertNode(array $node, int $depth, ?string $requiredType = null): void
    {
        if ($depth > (int) config('editor.max_depth')) {
            throw ValidationException::withMessages([
                'content' => ['Document nesting is too deep.'],
            ]);
        }

        $this->nodeCount++;

        if ($this->nodeCount > (int) config('editor.max_nodes')) {
            throw ValidationException::withMessages([
                'content' => ['Document contains too many nodes.'],
            ]);
        }

        $type = $node['type'] ?? null;

        if (! is_string($type) || ! in_array($type, config('editor.allowed_nodes'), true)) {
            throw ValidationException::withMessages([
                'content' => ['Document contains an unsupported node type.'],
            ]);
        }

        if ($requiredType !== null && $type !== $requiredType) {
            throw ValidationException::withMessages([
                'content' => ['Document root must be of type doc.'],
            ]);
        }

        $allowedKeys = ['type', 'content', 'text', 'marks', 'attrs'];

        foreach (array_keys($node) as $key) {
            if (! in_array($key, $allowedKeys, true)) {
                throw ValidationException::withMessages([
                    'content' => ["Unsupported node attribute [{$key}]."],
                ]);
            }
        }

        if ($type === 'heading') {
            $level = $node['attrs']['level'] ?? null;

            if (! is_int($level) || ! in_array($level, config('editor.heading_levels'), true)) {
                throw ValidationException::withMessages([
                    'content' => ['Heading level must be 1, 2, or 3.'],
                ]);
            }

            $this->assertOnlyAttrs($node, ['level']);
        } elseif (isset($node['attrs'])) {
            throw ValidationException::withMessages([
                'content' => ["Node [{$type}] does not allow attrs."],
            ]);
        }

        if ($type === 'text') {
            if (! is_string($node['text'] ?? null)) {
                throw ValidationException::withMessages([
                    'content' => ['Text nodes require a string text value.'],
                ]);
            }

            if (isset($node['content'])) {
                throw ValidationException::withMessages([
                    'content' => ['Text nodes cannot contain content.'],
                ]);
            }

            $this->assertMarks($node['marks'] ?? []);

            return;
        }

        if (isset($node['text'])) {
            throw ValidationException::withMessages([
                'content' => ["Node [{$type}] cannot contain text."],
            ]);
        }

        if (isset($node['marks'])) {
            throw ValidationException::withMessages([
                'content' => ["Node [{$type}] cannot contain marks."],
            ]);
        }

        if (in_array($type, ['hardBreak', 'horizontalRule'], true)) {
            if (isset($node['content'])) {
                throw ValidationException::withMessages([
                    'content' => ["Node [{$type}] cannot contain content."],
                ]);
            }

            return;
        }

        $content = $node['content'] ?? [];

        if (! is_array($content)) {
            throw ValidationException::withMessages([
                'content' => ['Node content must be an array.'],
            ]);
        }

        foreach ($content as $child) {
            if (! is_array($child)) {
                throw ValidationException::withMessages([
                    'content' => ['Each content item must be an object.'],
                ]);
            }

            $this->assertNode($child, $depth + 1);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $keys
     */
    private function assertOnlyAttrs(array $node, array $keys): void
    {
        $attrs = $node['attrs'] ?? null;

        if (! is_array($attrs)) {
            throw ValidationException::withMessages([
                'content' => ['Heading requires attrs.level.'],
            ]);
        }

        foreach (array_keys($attrs) as $key) {
            if (! in_array($key, $keys, true)) {
                throw ValidationException::withMessages([
                    'content' => ["Unsupported heading attribute [{$key}]."],
                ]);
            }
        }
    }

    private function assertMarks(mixed $marks): void
    {
        if ($marks === []) {
            return;
        }

        if (! is_array($marks)) {
            throw ValidationException::withMessages([
                'content' => ['Marks must be an array.'],
            ]);
        }

        foreach ($marks as $mark) {
            if (! is_array($mark)) {
                throw ValidationException::withMessages([
                    'content' => ['Each mark must be an object.'],
                ]);
            }

            $type = $mark['type'] ?? null;

            if (! is_string($type) || ! in_array($type, config('editor.allowed_marks'), true)) {
                throw ValidationException::withMessages([
                    'content' => ['Document contains an unsupported mark.'],
                ]);
            }

            if ($type === 'link') {
                $href = $mark['attrs']['href'] ?? null;

                if (! is_string($href) || ! preg_match('#^https?://#i', $href)) {
                    throw ValidationException::withMessages([
                        'content' => ['Link marks require an http or https href.'],
                    ]);
                }

                foreach (array_keys($mark['attrs'] ?? []) as $key) {
                    if (! in_array($key, ['href', 'target', 'rel'], true)) {
                        throw ValidationException::withMessages([
                            'content' => ["Unsupported link attribute [{$key}]."],
                        ]);
                    }
                }
            } elseif (isset($mark['attrs'])) {
                throw ValidationException::withMessages([
                    'content' => ["Mark [{$type}] does not allow attrs."],
                ]);
            }

            foreach (array_keys($mark) as $key) {
                if (! in_array($key, ['type', 'attrs'], true)) {
                    throw ValidationException::withMessages([
                        'content' => ["Unsupported mark field [{$key}]."],
                    ]);
                }
            }
        }
    }
}
