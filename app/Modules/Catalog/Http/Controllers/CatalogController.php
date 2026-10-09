<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\ListCatalogWorks;
use App\Modules\Catalog\Http\Requests\CatalogIndexRequest;
use App\Modules\Catalog\Http\Resources\CatalogWorkResource;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function index(
        CatalogIndexRequest $request,
        ListCatalogWorks $listCatalogWorks,
    ): JsonResponse {
        $paginator = $listCatalogWorks->handle([
            'q' => $request->searchQuery(),
            'status' => $request->validated('status'),
            'sort' => $request->sort(),
            'per_page' => $request->perPage(),
        ]);

        return CatalogWorkResource::collection($paginator)
            ->response()
            ->header('Cache-Control', 'no-store, private');
    }
}
