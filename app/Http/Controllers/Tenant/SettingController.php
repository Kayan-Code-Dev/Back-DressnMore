<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Setting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Setting::query()->orderBy('key')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:255', 'unique:tenant.settings,key'],
            'value' => ['nullable', 'string'],
        ]);

        $setting = Setting::query()->create($data);

        return ApiResponse::success($setting, 'Created', 201);
    }

    public function update(Request $request, int $setting): JsonResponse
    {
        $settingModel = Setting::query()->findOrFail($setting);

        $data = $request->validate([
            'value' => ['nullable', 'string'],
        ]);

        $settingModel->update($data);

        return ApiResponse::success($settingModel->fresh(), 'Updated');
    }
}
