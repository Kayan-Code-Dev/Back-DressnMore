<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\Payment;
use App\Services\Platform\SubscriptionPaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly SubscriptionPaymentService $paymentService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $paginator = $this->paymentService->paginate([
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ], $perPage);

        $rows = collect($paginator->items())
            ->map(fn (Payment $row) => $this->paymentService->present($row))
            ->all();

        return ApiResponse::paginated($paginator, $rows);
    }

    public function show(int $id): JsonResponse
    {
        $payment = $this->paymentService->findOrFail($id);

        return ApiResponse::success($this->paymentService->present($payment));
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        $payment = $this->paymentService->findOrFail($id);
        $payment = $this->paymentService->markPaid($payment, $request->user()?->id);

        return ApiResponse::success($this->paymentService->present($payment), 'تم تأكيد الدفع');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);
        $payment = $this->paymentService->findOrFail($id);
        $payment = $this->paymentService->reject($payment, $validated['notes'] ?? null);

        return ApiResponse::success($this->paymentService->present($payment), 'تم رفض الدفع');
    }

    public function refund(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);
        try {
            $payment = $this->paymentService->findOrFail($id);
            $payment = $this->paymentService->refund($payment, $validated['notes'] ?? null);
        } catch (\RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success($this->paymentService->present($payment), 'تم استرداد الدفع');
    }
}
