<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(string $message = 'Error', int $status = 400, mixed $errors = null): JsonResponse
    {
        $payload = [
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
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
