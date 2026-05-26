<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use stdClass;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        $metaPayload = $meta === [] ? new stdClass : $meta;

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $metaPayload,
        ], $status);
    }

    public static function paginated(
        LengthAwarePaginator $paginator,
        mixed $data,
        string $message = 'Success',
        int $status = 200
    ): JsonResponse {
        return self::success(
            data: $data,
            message: $message,
            status: $status,
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public static function error(string $message = 'Error', int $status = 400, mixed $errors = []): JsonResponse
    {
        $errorsPayload = match (true) {
            $errors === null => new stdClass,
            $errors === [] => new stdClass,
            default => $errors,
        };

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errorsPayload,
        ], $status);
    }

    public static function validation(mixed $errors, string $message = 'Validation failed'): JsonResponse
    {
        return self::error($message, 422, $errors);
    }

    public static function unauthorized(string $message = 'Unauthenticated'): JsonResponse
    {
        return self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return self::error($message, 403);
    }

    public static function notFound(string $message = 'Not found'): JsonResponse
    {
        return self::error($message, 404);
    }

    public static function serverError(string $message = 'Server error'): JsonResponse
    {
        return self::error($message, 500);
    }
}
