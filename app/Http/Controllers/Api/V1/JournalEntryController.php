<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JournalEntryResource;
use App\Http\Responses\ApiResponse;
use App\Services\Accounting\JournalEntryQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function index(Request $request, JournalEntryQueryService $service): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        return ApiResponse::paginated(
            $service->list($request->only(['from', 'to']), $perPage),
            JournalEntryResource::class,
        );
    }
}
