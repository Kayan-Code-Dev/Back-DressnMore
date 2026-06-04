<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\PurchaseOrder\ReceivePurchaseOrderRequest;
use App\Http\Requests\Tenant\PurchaseOrder\ReturnPurchaseOrderRequest;
use App\Http\Requests\Tenant\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\Tenant\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Http\Resources\Tenant\PurchaseOrderResource;
use App\Services\Tenant\PurchaseOrderService;
use App\Support\ApiResponse;
use App\Support\CsvExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $purchaseOrderService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $purchaseOrders = $this->purchaseOrderService->paginate([
            'search' => $request->query('search'),
            'supplier_id' => $request->query('supplier_id'),
            'branch_id' => $request->query('branch_id'),
            'category_id' => $request->query('category_id'),
            'subcategory_id' => $request->query('subcategory_id'),
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'is_returned' => $request->query('is_returned'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ], $perPage);

        return ApiResponse::paginated(
            $purchaseOrders,
            PurchaseOrderResource::collection($purchaseOrders->items())->resolve()
        );
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $purchaseOrder = $this->purchaseOrderService->create($request->validated(), $request->user()?->id);

        return ApiResponse::success(new PurchaseOrderResource($purchaseOrder), 'Purchase order created', 201);
    }

    public function show(int $purchaseOrder): JsonResponse
    {
        $purchaseOrderModel = $this->purchaseOrderService->findOrFail($purchaseOrder);

        return ApiResponse::success(new PurchaseOrderResource($purchaseOrderModel));
    }

    public function update(UpdatePurchaseOrderRequest $request, int $purchaseOrder): JsonResponse
    {
        $purchaseOrderModel = $this->purchaseOrderService->findOrFail($purchaseOrder);
        $purchaseOrderModel = $this->purchaseOrderService->update(
            purchaseOrder: $purchaseOrderModel,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return ApiResponse::success(new PurchaseOrderResource($purchaseOrderModel), 'Purchase order updated');
    }

    public function destroy(int $purchaseOrder): JsonResponse
    {
        $purchaseOrderModel = $this->purchaseOrderService->findOrFail($purchaseOrder);
        $this->purchaseOrderService->delete($purchaseOrderModel);

        return ApiResponse::success(null, 'Purchase order deleted');
    }

    public function returnOrder(ReturnPurchaseOrderRequest $request, int $purchaseOrder): JsonResponse
    {
        $purchaseOrderModel = $this->purchaseOrderService->findOrFail($purchaseOrder);
        $purchaseOrderModel = $this->purchaseOrderService->returnOrder($purchaseOrderModel, $request->validated());

        return ApiResponse::success(new PurchaseOrderResource($purchaseOrderModel), 'Purchase order returned');
    }

    public function receive(ReceivePurchaseOrderRequest $request, int $purchaseOrder): JsonResponse
    {
        $purchaseOrderModel = $this->purchaseOrderService->findOrFail($purchaseOrder);
        $purchaseOrderModel = $this->purchaseOrderService->receive(
            purchaseOrder: $purchaseOrderModel,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return ApiResponse::success(new PurchaseOrderResource($purchaseOrderModel), 'Purchase order received');
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->purchaseOrderService->exportRows([
            'search' => $request->query('search'),
            'supplier_id' => $request->query('supplier_id'),
            'branch_id' => $request->query('branch_id'),
            'category_id' => $request->query('category_id'),
            'subcategory_id' => $request->query('subcategory_id'),
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'is_returned' => $request->query('is_returned'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ]);

        return CsvExporter::download(
            filename: 'purchase-orders.csv',
            headers: ['ID', 'PO Number', 'Supplier ID', 'Branch ID', 'Status', 'Returned', 'Total', 'Paid', 'Remaining', 'Order Date'],
            rows: $rows
        );
    }
}
