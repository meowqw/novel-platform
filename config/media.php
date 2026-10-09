<?php

return [

    'disk' => env('MEDIA_DISK', 'media'),

    'max_upload_bytes' => (int) env('MEDIA_MAX_UPLOAD_BYTES', 10 * 1024 * 1024),

    'max_megapixels' => (float) env('MEDIA_MAX_MEGAPIXELS', 25),

    'max_edge_px' => (int) env('MEDIA_MAX_EDGE_PX', 10_000),

    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],

    'cover' => [
        'max_edge_px' => (int) env('MEDIA_COVER_MAX_EDGE_PX', 2400),
        'thumb_max_width' => (int) env('MEDIA_COVER_THUMB_MAX_WIDTH', 320),
        'thumb_max_height' => (int) env('MEDIA_COVER_THUMB_MAX_HEIGHT', 450),
    ],

    'chapter_image' => [
        'max_width_px' => (int) env('MEDIA_CHAPTER_MAX_WIDTH_PX', 1600),
    ],

    'private_url_ttl_seconds' => (int) env('MEDIA_PRIVATE_URL_TTL_SECONDS', 3600),

    'public_cache_seconds' => (int) env('MEDIA_PUBLIC_CACHE_SECONDS', 60),

];
