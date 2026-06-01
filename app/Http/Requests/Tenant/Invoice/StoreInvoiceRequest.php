<?php

namespace App\Http\Requests\Tenant\Invoice;

use App\Enums\PaymentMethod;
use App\Enums\SecurityDepositStatus;
use App\Enums\VatType;
use App\Models\Tenant\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInvoiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
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

            'items' => ['required', 'array', 'min:1'],
            'items.*.dress_id' => ['nullable', 'integer', Rule::exists('tenant.dresses', 'id')->whereNull('deleted_at')],
            'items.*.item_type' => ['nullable', 'string', 'max:100'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],

            'initial_payment' => ['nullable', 'array'],
            'initial_payment.amount' => ['required_with:initial_payment', 'numeric', 'gt:0'],
            'initial_payment.method' => ['nullable', 'string', Rule::in(PaymentMethod::values())],
            'initial_payment.reference' => ['nullable', 'string', 'max:255'],
            'initial_payment.paid_at' => ['nullable', 'date'],
            'initial_payment.notes' => ['nullable', 'string'],

            'security_deposit_payment' => ['nullable', 'array'],
            'security_deposit_payment.amount' => ['required_with:security_deposit_payment', 'numeric', 'gt:0'],
            'security_deposit_payment.method' => ['nullable', 'string', Rule::in(PaymentMethod::values())],
            'security_deposit_payment.reference' => ['nullable', 'string', 'max:255'],
            'security_deposit_payment.paid_at' => ['nullable', 'date'],
            'security_deposit_payment.notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = (string) $this->input('type');
            $total = $this->estimateInvoiceTotal();
            $initialAmount = round((float) data_get($this->all(), 'initial_payment.amount', 0), 2);
            $depositExpected = round((float) $this->input('security_deposit', 0), 2);
            $depositPaymentAmount = round((float) data_get($this->all(), 'security_deposit_payment.amount', 0), 2);

            if ($initialAmount > 0 && $initialAmount > $total + 0.009) {
                $validator->errors()->add('initial_payment.amount', 'مبلغ الدفعة الأولية يتجاوز إجمالي الفاتورة');
            }

            if ($depositPaymentAmount > 0 && $type !== Invoice::TYPE_RENT) {
                $validator->errors()->add('security_deposit_payment', 'تحصيل التأمين مسموح فقط لفواتير الإيجار');
            }

            if ($depositPaymentAmount > 0 && $depositExpected <= 0) {
                $validator->errors()->add('security_deposit_payment', 'يجب تحديد مبلغ التأمين قبل تحصيله');
            }

            if ($depositPaymentAmount > $depositExpected + 0.009) {
                $validator->errors()->add('security_deposit_payment.amount', 'مبلغ تحصيل التأمين يتجاوز مبلغ التأمين المتوقع');
            }
        });
    }

    private function estimateInvoiceTotal(): float
    {
        $items = $this->input('items', []);
        if (! is_array($items)) {
            return 0.0;
        }

        $subtotal = 0.0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);
            $lineTotal = isset($item['total'])
                ? round((float) $item['total'], 2)
                : round($quantity * $unitPrice, 2);
            $subtotal += $lineTotal;
        }

        $discount = round((float) $this->input('discount', 0), 2);
        $tax = round((float) $this->input('tax', 0), 2);

        return max(0, round($subtotal - $discount + $tax, 2));
    }
}
