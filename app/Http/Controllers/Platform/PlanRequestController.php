<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlanRequest\StorePlanRequestRequest;
use App\Http\Resources\Platform\PlanRequestResource;
use App\Http\Resources\Platform\TenantResource;
use App\Models\Central\PaymentGateway;
use App\Models\Central\PlanRequest;
use App\Services\Platform\PlanRequestService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PlanRequestController extends Controller
{
    public function __construct(private readonly PlanRequestService $planRequestService) {}

    public function paymentGateways(): JsonResponse
    {
        $gateways = PaymentGateway::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get(['id', 'name', 'type', 'instructions', 'account_holder', 'account_number', 'bank_name', 'iban']);

        return ApiResponse::success([
            'gateways' => $gateways,
        ]);
    }

    public function store(StorePlanRequestRequest $request): JsonResponse
    {
        try {
            $payload = $request->validated();
            if ($request->hasFile('payment_proof')) {
                $payload['payment_proof'] = $request->file('payment_proof');
            }
            $result = $this->planRequestService->store($payload);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        $status = ($result['status'] ?? 'pending') === 'approved' ? 201 : 202;

        return ApiResponse::success($result, (string) ($result['message'] ?? 'Plan request submitted'), $status);
    }

    public function publicShow(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $planRequest = PlanRequest::query()
            ->with(['plan', 'paymentGateway'])
            ->findOrFail($id);

        try {
            $result = $this->planRequestService->publicStatus($planRequest, (string) $validated['email']);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 404);
        }

        return ApiResponse::success($result);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));

        $requests = $this->planRequestService->paginate([
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ], $perPage);

        return ApiResponse::paginated(
            $requests,
            PlanRequestResource::collection($requests->items())->resolve(),
        );
    }

    public function show(int $id): JsonResponse
    {
        $planRequest = PlanRequest::query()
            ->with(['plan', 'paymentGateway', 'tenant'])
            ->findOrFail($id);

        return ApiResponse::success(new PlanRequestResource($planRequest));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $planRequest = PlanRequest::query()
            ->with(['plan', 'paymentGateway', 'tenant'])
            ->findOrFail($id);

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:pending,payment_submitted,approved,rejected'],
        ]);

        if (! array_key_exists('status', $validated) && ! array_key_exists('admin_notes', $validated)) {
            return ApiResponse::error('Nothing to update', 422);
        }

        if (($validated['status'] ?? null) === null) {
            $planRequest->update(['admin_notes' => $validated['admin_notes'] ?? $planRequest->admin_notes]);

            return ApiResponse::success(new PlanRequestResource($planRequest->refresh()->load(['plan', 'paymentGateway', 'tenant'])));
        }

        try {
            $result = $this->planRequestService->updateStatus(
                $planRequest,
                $validated,
                $request->user()?->id,
            );
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        if (is_array($result)) {
            return ApiResponse::success([
                'request' => new PlanRequestResource($result['request']),
                'tenant' => new TenantResource($result['tenant']),
                'subscription' => $result['subscription'],
                'admin' => $result['admin'],
                'hostname_label' => $result['hostname_label'],
            ], 'تم الموافقة على الطلب وإنشاء الحساب بنجاح');
        }

        return ApiResponse::success(new PlanRequestResource($result), 'Plan request updated');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $planRequest = PlanRequest::query()->with(['plan', 'paymentGateway', 'tenant'])->findOrFail($id);

        try {
            $result = $this->planRequestService->updateStatus(
                $planRequest,
                ['status' => 'approved'],
                $request->user()?->id,
            );
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        if (! is_array($result)) {
            return ApiResponse::error('Approval failed', 500);
        }

        return ApiResponse::success([
            'tenant' => new TenantResource($result['tenant']),
            'request' => new PlanRequestResource($result['request']),
            'admin' => $result['admin'],
            'hostname_label' => $result['hostname_label'],
        ], 'تم الموافقة على الطلب وإنشاء الحساب بنجاح');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $planRequest = PlanRequest::query()->with(['plan', 'paymentGateway', 'tenant'])->findOrFail($id);

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string'],
        ]);

        try {
            $updated = $this->planRequestService->reject(
                $planRequest,
                $validated['admin_notes'] ?? null,
                $request->user()?->id,
            );
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success(new PlanRequestResource($updated), 'تم رفض الطلب');
    }

    public function destroy(int $id): JsonResponse
    {
        $planRequest = PlanRequest::query()->findOrFail($id);

        if ($planRequest->status === 'approved') {
            return ApiResponse::error('Approved requests cannot be deleted', 422);
        }

        $planRequest->delete();

        return ApiResponse::success(null, 'تم حذف الطلب');
    }
}
