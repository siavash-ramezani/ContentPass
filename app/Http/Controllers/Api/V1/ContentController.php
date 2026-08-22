<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContentResource;
use App\Services\ContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class ContentController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly ContentAccessService $contentAccess)
    {
        //
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();
        $published = $this->contentAccess->publishedContent();

        $perPage = max(1, min((int) $request->integer('per_page', 15), 50));
        $page = LengthAwarePaginator::resolveCurrentPage();

        $items = $published->forPage($page, $perPage)->values();

        $items->each(function ($content) use ($user) {
            $content->accessible = $this->contentAccess->isAccessibleTo($content, $user);
        });

        $paginator = new LengthAwarePaginator(
            $items,
            $published->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->success([
            'items' => ContentResource::collection($paginator->getCollection()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
