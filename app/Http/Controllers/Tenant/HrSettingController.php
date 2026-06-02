<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Hr\Setting\UpdateHrSettingsRequest;
use App\Services\Tenant\HrSettingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HrSettingController extends Controller
{
    public function __construct(private readonly HrSettingService $hrSettingService) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->hrSettingService->all());
    }

    public function update(UpdateHrSettingsRequest $request): JsonResponse
    {
        /** @var array<string, array<string, mixed>> $settings */
        $settings = $request->validated()['settings'];
        $updated = $this->hrSettingService->update($settings);

        return ApiResponse::success($updated, 'HR settings updated');
    }
}
