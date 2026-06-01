<?php

namespace App\Http\Requests\Tenant\Tailoring;

use App\Enums\TailoringPriority;
use App\Enums\TailoringProductionStatus;
use App\Models\Tenant\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTailoringOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('tenant.customers', 'id')->whereNull('deleted_at')],
            'branch_id' => ['nullable', 'integer', Rule::exists('tenant.branches', 'id')->whereNull('deleted_at')],
            'status' => ['nullable', 'string', Rule::in(Invoice::statuses())],
            'tailoring_due_date' => ['nullable', 'date'],
            'fitting_date' => ['nullable', 'date'],
            'next_follow_up_date' => ['nullable', 'date'],
            'visit_datetime' => ['nullable', 'date'],
            'occasion_datetime' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', Rule::in(TailoringPriority::values())],
            'assigned_tailor_id' => ['nullable', 'integer'],
            'design_notes' => ['nullable', 'string'],
            'workshop_notes' => ['nullable', 'string'],
            'tailoring_notes' => ['nullable', 'string'],
            'order_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'measurements' => ['nullable', 'array'],
            'measurements.*.label' => ['required_with:measurements', 'string', 'max:120'],
            'measurements.*.value' => ['required_with:measurements', 'string', 'max:120'],
            'measurements.*.unit' => ['nullable', 'string', 'max:20'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.dress_id' => ['nullable', 'integer', Rule::exists('tenant.dresses', 'id')->whereNull('deleted_at')],
            'items.*.item_type' => ['nullable', 'string', 'max:100'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'initial_payment' => ['nullable', 'array'],
            'initial_payment.amount' => ['nullable', 'numeric', 'min:0.01'],
            'initial_payment.method' => ['nullable', 'string'],
        ];
    }
}
