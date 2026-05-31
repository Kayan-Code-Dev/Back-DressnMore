<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\FactoryService;
use App\Support\ApiResponse;
use App\Support\Tenant\HrOperationsPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FactoryController extends Controller
{
    public function __construct(private readonly FactoryService $factoryService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $rows = $this->factoryService->paginate(['search' => $request->query('search')], $perPage);
        $data = collect($rows->items())->map(fn ($row) => HrOperationsPresenter::factory($row))->all();

        return ApiResponse::paginated($rows, $data);
    }
}
