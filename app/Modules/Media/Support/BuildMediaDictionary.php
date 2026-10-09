<?php

namespace App\Modules\Media\Support;

use App\Modules\Media\Models\MediaAsset;
use Illuminate\Support\Collection;

class BuildMediaDictionary
{
    public function __construct(
        private readonly MediaIdExtractor $extractor,
        private readonly MediaUrlBuilder $urls,
    ) {}

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, array{url: string, width: int, height: int, mime_type: string, byte_size: int}>
     */
    public function forOwner(array $document, string $ownerId): array
    {
        $ids = $this->extractor->extract($document);

        if ($ids === []) {
            return [];
        }

        /** @var Collection<string, MediaAsset> $assets */
        $assets = MediaAsset::query()
            ->whereIn('id', $ids)
            ->where('owner_id', $ownerId)
            ->get()
            ->keyBy(static fn (MediaAsset $asset): string => $asset->id);

        $dictionary = [];

        foreach ($ids as $id) {
            $asset = $assets->get($id);

            if (! $asset instanceof MediaAsset) {
                continue;
            }

            $dictionary[$id] = $this->urls->privateDictionaryEntry($asset);
        }

        return $dictionary;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, array{url: string, width: int, height: int, mime_type: string, byte_size: int}>
     */
    public function forPublic(array $document): array
    {
        $ids = $this->extractor->extract($document);

        if ($ids === []) {
            return [];
        }

        /** @var Collection<string, MediaAsset> $assets */
        $assets = MediaAsset::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(static fn (MediaAsset $asset): string => $asset->id);

        $dictionary = [];

        foreach ($ids as $id) {
            $asset = $assets->get($id);

            if (! $asset instanceof MediaAsset) {
                continue;
            }

            $dictionary[$id] = $this->urls->publicDictionaryEntry($asset);
        }

        return $dictionary;
    }
}
