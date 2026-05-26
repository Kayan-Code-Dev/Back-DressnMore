<?php

namespace App\Http\Requests\Tenant\Customer;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:30'],
            'phone2' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city_id' => ['nullable', 'integer'],
            'national_id' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', Rule::in(CustomerSource::values())],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(CustomerStatus::values())],
        ];
    }
}
