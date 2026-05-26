<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CashMovement\StoreCashMovementRequest;
use App\Http\Resources\Tenant\CashMovementResource;
use App\Services\Tenant\CashMovementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashMovementController extends Controller
{
    public function __construct(private readonly CashMovementService $cashMovementService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $cashMovements = $this->cashMovementService->paginate([
            'search' => $request->query('search'),
            'type' => $request->query('type'),
            'direction' => $request->query('direction'),
            'method' => $request->query('method'),
            'cashbox_id' => $request->query('cashbox_id'),
            'is_reversed' => $request->query('is_reversed'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ], $perPage);

        return ApiResponse::paginated($cashMovements, CashMovementResource::collection($cashMovements->items())->resolve());
    }

    public function store(StoreCashMovementRequest $request): JsonResponse
    {
        $cashMovement = $this->cashMovementService->createManual(
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return ApiResponse::success(new CashMovementResource($cashMovement), 'Cash movement created', 201);
    }
}
