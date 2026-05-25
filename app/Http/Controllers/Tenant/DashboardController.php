<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Dress;
use App\Models\Tenant\Invoice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'customers_count' => Customer::query()->count(),
            'dresses_count' => Dress::query()->count(),
            'invoices_count' => Invoice::query()->count(),
        ]);
    }
}
