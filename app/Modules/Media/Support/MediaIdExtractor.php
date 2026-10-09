<?php

namespace App\Modules\Media\Support;

class MediaIdExtractor
{
    /**
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    public function extract(array $document): array
    {
        $ids = [];
        $this->walk($document, $ids);

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $ids
     */
    private function walk(array $node, array &$ids): void
    {
        if (($node['type'] ?? null) === 'image') {
            $mediaId = $node['attrs']['mediaId'] ?? null;

            if (is_string($mediaId) && $mediaId !== '') {
                $ids[] = $mediaId;
            }
        }

        foreach ($node['content'] ?? [] as $child) {
            if (is_array($child)) {
                $this->walk($child, $ids);
            }
        }
    }
}
