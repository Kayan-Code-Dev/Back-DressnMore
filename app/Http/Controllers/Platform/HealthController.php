<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $centralConnection = false;

        try {
            DB::connection('central')->select('SELECT 1');
            $centralConnection = true;
        } catch (Throwable) {
            $centralConnection = false;
        }

        return ApiResponse::success([
            'app_name' => config('app.name'),
            'environment' => config('app.env'),
            'central_database_connection' => $centralConnection,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
