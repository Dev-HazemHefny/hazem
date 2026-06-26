<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResponse
{
    public static function success(mixed $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => array_merge(self::baseMeta(), $meta),
        ], $status);
    }

    public static function paginated(LengthAwarePaginator $paginator, ?string $resourceClass = null): JsonResponse
    {
        $items = $resourceClass
            ? $resourceClass::collection($paginator->items())
            : $paginator->items();

        $data = $items instanceof JsonResource ? $items->resolve() : $items;

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => array_merge(self::baseMeta(), [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]),
        ]);
    }

    public static function error(
        string $code,
        string $message,
        int $status = 400,
        ?array $details = null,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'meta' => self::baseMeta(),
        ];

        if ($details !== null) {
            $payload['error']['details'] = $details;
        }

        return response()->json($payload, $status);
    }

    private static function baseMeta(): array
    {
        $request = app(Request::class);

        return [
            'request_id' => $request->attributes->get('request_id'),
        ];
    }
}
