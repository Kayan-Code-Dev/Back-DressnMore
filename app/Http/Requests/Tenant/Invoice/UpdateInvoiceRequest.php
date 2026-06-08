<?php

namespace App\Http\Requests\Tenant\Invoice;

use App\Enums\SecurityDepositStatus;
use App\Enums\VatType;
use App\Models\Tenant\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $type = $this->input('type');
        if (is_string($type)) {
            $normalized = strtolower(trim($type));
            if ($normalized === 'sale') {
                $this->merge(['type' => Invoice::TYPE_SELL]);
            } elseif ($normalized === 'rental') {
                $this->merge(['type' => Invoice::TYPE_RENT]);
            }
        }

        if (! $this->has('customer_id') && $this->has('client_id')) {
            $this->merge(['customer_id' => $this->input('client_id')]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('tenant.customers', 'id')->whereNull('deleted_at')],
            'branch_id' => ['nullable', 'integer', Rule::exists('tenant.branches', 'id')->whereNull('deleted_at')],
            'type' => ['required', 'string', Rule::in(Invoice::types())],
            'status' => ['nullable', 'string', Rule::in(Invoice::statuses())],
            'allow_cancelled_update' => ['nullable', 'boolean'],

            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', Rule::in(VatType::values())],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],

            'rent_start_date' => ['nullable', 'date', 'required_if:type,rent'],
            'rent_end_date' => ['nullable', 'date', 'required_if:type,rent', 'after_or_equal:rent_start_date'],
            'delivery_date' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date'],
            'security_deposit' => ['nullable', 'numeric', 'min:0'],
            'security_deposit_status' => ['nullable', 'string', Rule::in(SecurityDepositStatus::values())],

            'tailoring_due_date' => ['nullable', 'date'],
            'visit_datetime' => ['nullable', 'date'],
            'occasion_datetime' => ['nullable', 'date'],
            'days_of_rent' => ['nullable', 'integer', 'min:1'],
            'tailoring_notes' => ['nullable', 'string'],

            'notes' => ['nullable', 'string'],
            'order_notes' => ['nullable', 'string'],

            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.dress_id' => ['nullable', 'integer', Rule::exists('tenant.dresses', 'id')->whereNull('deleted_at')],
            'items.*.item_type' => ['nullable', 'string', 'max:100'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
