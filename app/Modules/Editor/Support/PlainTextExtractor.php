<?php

namespace App\Modules\Editor\Support;

class PlainTextExtractor
{
    /**
     * @param  array<string, mixed>  $document
     */
    public function extract(array $document): string
    {
        $parts = [];
        $this->walk($document, $parts);

        $text = implode("\n", array_map(
            static fn (string $line): string => rtrim($line),
            $parts,
        ));

        return trim($text);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $parts
     */
    private function walk(array $node, array &$parts, string &$buffer = ''): void
    {
        $type = $node['type'] ?? '';

        if ($type === 'text') {
            $buffer .= (string) ($node['text'] ?? '');

            return;
        }

        if ($type === 'hardBreak') {
            $buffer .= "\n";

            return;
        }

        if ($type === 'horizontalRule') {
            $this->flush($parts, $buffer);

            return;
        }

        $isBlock = in_array($type, [
            'paragraph', 'heading', 'blockquote', 'listItem', 'bulletList', 'orderedList', 'doc',
        ], true);

        if ($isBlock && $type !== 'doc' && $type !== 'bulletList' && $type !== 'orderedList') {
            $childBuffer = '';

            foreach ($node['content'] ?? [] as $child) {
                if (is_array($child)) {
                    $this->walk($child, $parts, $childBuffer);
                }
            }

            $this->flush($parts, $childBuffer);

            return;
        }

        foreach ($node['content'] ?? [] as $child) {
            if (is_array($child)) {
                $this->walk($child, $parts, $buffer);
            }
        }
    }

    /**
     * @param  list<string>  $parts
     */
    private function flush(array &$parts, string &$buffer): void
    {
        if ($buffer !== '') {
            $parts[] = $buffer;
            $buffer = '';
        }
    }

    public function characterCount(string $plainText): int
    {
        return mb_strlen($plainText);
    }

    public function wordCount(string $plainText): int
    {
        if (trim($plainText) === '') {
            return 0;
        }

        $parts = preg_split('/\s+/u', trim($plainText)) ?: [];

        return count(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}
