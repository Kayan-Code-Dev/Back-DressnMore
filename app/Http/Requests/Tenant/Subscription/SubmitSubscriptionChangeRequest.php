<?php

namespace App\Http\Requests\Tenant\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class SubmitSubscriptionChangeRequest extends FormRequest
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
            'plan_code' => ['required', 'string', 'max:120'],
            'payment_gateway_id' => ['required', 'integer', 'min:1'],
            'payment_reference' => ['required', 'string', 'max:255'],
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_code.required' => 'يرجى اختيار الباقة',
            'payment_gateway_id.required' => 'يرجى اختيار بوابة الدفع',
            'payment_reference.required' => 'يرجى إدخال رقم المحفظة أو الحساب الذي دفعت منه',
            'payment_proof.required' => 'يرجى إرفاق صورة إيصال التحويل',
            'payment_proof.file' => 'ملف إثبات الدفع غير صالح',
            'payment_proof.mimes' => 'صيغة إثبات الدفع يجب أن تكون JPG أو PNG أو WEBP أو PDF',
            'payment_proof.max' => 'حجم صورة الإيصال يجب ألا يتجاوز 10 ميجابايت',
        ];
    }
}
