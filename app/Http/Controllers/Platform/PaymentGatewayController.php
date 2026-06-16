<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PaymentGateway\StorePaymentGatewayRequest;
use App\Http\Requests\Platform\PaymentGateway\UpdatePaymentGatewayRequest;
use App\Http\Resources\Platform\PaymentGatewayResource;
use App\Services\Platform\PaymentGatewayService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    public function __construct(private readonly PaymentGatewayService $paymentGatewayService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 50)));
        $gateways = $this->paymentGatewayService->paginate([
            'search' => $request->query('search'),
            'is_active' => $request->query('is_active'),
        ], $perPage);

        return ApiResponse::paginated(
            $gateways,
            PaymentGatewayResource::collection($gateways->items())->resolve()
        );
    }

    public function store(StorePaymentGatewayRequest $request): JsonResponse
    {
        $gateway = $this->paymentGatewayService->create($request->validated());

        return ApiResponse::success(new PaymentGatewayResource($gateway), 'Payment gateway created', 201);
    }

    public function show(int $paymentGateway): JsonResponse
    {
        return ApiResponse::success(new PaymentGatewayResource(
            $this->paymentGatewayService->findOrFail($paymentGateway)
        ));
    }

    public function update(UpdatePaymentGatewayRequest $request, int $paymentGateway): JsonResponse
    {
        $gateway = $this->paymentGatewayService->update(
            $this->paymentGatewayService->findOrFail($paymentGateway),
            $request->validated()
        );

        return ApiResponse::success(new PaymentGatewayResource($gateway), 'Payment gateway updated');
    }

    public function destroy(int $paymentGateway): JsonResponse
    {
        $this->paymentGatewayService->delete($this->paymentGatewayService->findOrFail($paymentGateway));

        return ApiResponse::success(null, 'Payment gateway deleted');
    }

    public function toggleStatus(int $paymentGateway): JsonResponse
    {
        $gateway = $this->paymentGatewayService->toggleStatus(
            $this->paymentGatewayService->findOrFail($paymentGateway)
        );

        return ApiResponse::success(new PaymentGatewayResource($gateway), 'Payment gateway status updated');
    }
}
