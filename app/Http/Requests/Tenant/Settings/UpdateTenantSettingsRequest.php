<?php

namespace App\Http\Requests\Tenant\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'app' => ['sometimes', 'array'],
            'app.timezone' => ['sometimes', 'string', 'max:100'],
            'app.currency' => ['sometimes', 'string', 'size:3', 'in:EGP,USD,SAR,AED,KWD,QAR,BHD,OMR,JOD'],

            'invoice' => ['sometimes', 'array'],
            'invoice.tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'invoice.invoice_prefix' => ['sometimes', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],

            'rental' => ['sometimes', 'array'],
            'rental.late_fee_per_day' => ['sometimes', 'numeric', 'min:0', 'max:99999'],

            'company' => ['sometimes', 'array'],
            'company.name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company.phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'company.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'company.address' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'app.currency.in' => 'العملة غير مدعومة. العملات المدعومة: EGP, USD, SAR, AED, KWD, QAR, BHD, OMR, JOD',
            'app.currency.size' => 'رمز العملة يجب أن يكون 3 أحرف (مثل EGP)',
            'invoice.tax_rate.min' => 'نسبة الضريبة يجب أن تكون 0% على الأقل',
            'invoice.tax_rate.max' => 'نسبة الضريبة يجب ألا تتجاوز 100%',
            'invoice.invoice_prefix.regex' => 'بادئة الفاتورة يجب أن تحتوي على أحرف إنجليزية وأرقام وشرطات فقط',
            'rental.late_fee_per_day.min' => 'رسوم التأخير يجب ألا تكون سالبة',
            'rental.late_fee_per_day.max' => 'رسوم التأخير مرتفعة جداً',
        ];
    }
}
