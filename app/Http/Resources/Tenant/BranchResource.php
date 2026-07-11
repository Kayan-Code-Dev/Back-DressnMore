<?php

namespace App\Http\Resources\Tenant;

use App\Services\Tenant\AppSettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $appSettings = app(AppSettingService::class)->present();

        // Count products (dresses) for this branch
        $productsCount = $this->dresses()
            ->whereNull('deleted_at')
            ->count();

        // Count employees (legacy + hr)
        $legacyEmployees = \DB::connection('tenant')
            ->table('employees')
            ->where('branch_id', $this->id)
            ->count();
        $hrEmployees = \DB::connection('tenant')
            ->table('hr_employees')
            ->where('branch_id', $this->id)
            ->whereNull('deleted_at')
            ->count();
        $employeesCount = $legacyEmployees + $hrEmployees;

        // Count invoices (all types: sale, rental, tailoring)
        $invoicesCount = $this->invoices()
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->count();

        return [
            'id' => $this->id,
            'branch_code' => $this->branch_code ?: $this->code,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone,
            'vat_enabled' => (bool) $this->vat_enabled,
            'vat_type' => $this->vat_type,
            'vat_value' => $this->vat_value,
            'currency' => $appSettings['currency'],
            'currency_symbol' => $appSettings['currency_symbol'],
            'street' => $this->street,
            'building' => $this->building,
            'city_id' => $this->city_id,
            'address' => $this->address,
            'notes' => $this->notes,
            'inventory_name' => $this->inventory_name,
            'image' => $this->image,
            'logo' => $this->logo,
            'cover' => $this->cover,
            'status' => $this->status,
            // Real stats
            'products_count' => $productsCount,
            'employees_count' => $employeesCount,
            'invoices_count' => $invoicesCount,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
