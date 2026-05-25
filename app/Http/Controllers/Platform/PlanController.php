<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\Plan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Plan::query()->with('features')->orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:120', 'unique:central.plans,slug'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'billing_cycle' => ['required', 'string', 'in:monthly,yearly'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $plan = Plan::query()->create($data);

        return ApiResponse::success($plan, 'Plan created', 201);
    }
}
