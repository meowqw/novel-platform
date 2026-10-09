<?php

use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Editor\EditorServiceProvider;
use App\Modules\Example\ExampleServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Library\LibraryServiceProvider;
use App\Modules\Media\MediaServiceProvider;
use App\Modules\Publishing\PublishingServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    ExampleServiceProvider::class,
    IdentityServiceProvider::class,
    LibraryServiceProvider::class,
    EditorServiceProvider::class,
    PublishingServiceProvider::class,
    CatalogServiceProvider::class,
    MediaServiceProvider::class,
];
