<?php

namespace App\Http\Requests\Platform\PlanRequest;

use App\Models\Central\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StorePlanRequestRequest extends FormRequest
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
        $planId = (int) $this->input('plan_id');
        $plan = $planId > 0 ? Plan::query()->find($planId) : null;
        $isPaidPlan = $plan !== null && (float) $plan->price > 0;

        return [
            'plan_id' => ['required', 'integer', 'exists:central.plans,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::min(8)],
            'phone' => ['required', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'payment_gateway_id' => [$isPaidPlan ? 'required' : 'nullable', 'integer', 'exists:central.payment_gateways,id'],
            'payment_reference' => [$isPaidPlan ? 'required' : 'nullable', 'string', 'max:100'],
            'payment_proof' => array_merge(
                $isPaidPlan ? ['required'] : ['nullable'],
                ['file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_reference.required' => 'يرجى إدخال رقم المحفظة أو الحساب الذي دفعت منه.',
            'payment_proof.required' => 'يرجى إرفاق صورة إيصال التحويل.',
            'payment_proof.image' => 'يجب أن يكون إيصال الدفع صورة (JPG أو PNG).',
            'payment_proof.mimes' => 'يجب أن يكون إيصال الدفع صورة (JPG أو PNG).',
            'payment_gateway_id.required' => 'يرجى اختيار بوابة الدفع.',
        ];
    }
}
