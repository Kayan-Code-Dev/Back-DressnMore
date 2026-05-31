<?php

namespace App\Support\Tenant;

use App\Models\Tenant\Employee;
use App\Models\Tenant\EmployeeCustody;
use App\Models\Tenant\EmployeeSalary;
use App\Models\Tenant\Factory;
use App\Models\Tenant\Notification;
use App\Models\Tenant\Workshop;
use App\Models\Tenant\WorkshopCloth;
use App\Models\Tenant\WorkshopTransfer;

class HrOperationsPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function employee(Employee $employee): array
    {
        $employee->loadMissing('branch');

        return [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'name' => $employee->name,
            'email' => $employee->email ?? '',
            'phone' => $employee->phone ?? '',
            'job_title' => $employee->job_title ?? '',
            'branch_name' => $employee->branch?->name ?? '',
            'employment_status' => $employee->employment_status,
            'base_salary' => (float) $employee->base_salary,
            'hire_date' => $employee->hire_date?->toDateString() ?? '',
            'transport_allowance' => (float) $employee->transport_allowance,
            'housing_allowance' => (float) $employee->housing_allowance,
            'other_allowances' => (float) $employee->other_allowances,
            'roles' => $employee->roles ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function custody(EmployeeCustody $custody): array
    {
        $custody->loadMissing('employee');

        return [
            'id' => $custody->id,
            'employee_id' => $custody->employee_id,
            'employee_name' => $custody->employee?->name ?? '',
            'type' => $custody->type,
            'description' => $custody->description,
            'value' => (float) $custody->value,
            'issued_at' => $custody->issued_at?->toDateString() ?? '',
            'expires_at' => $custody->expires_at?->toDateString(),
            'status' => $custody->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function salary(EmployeeSalary $salary): array
    {
        $salary->loadMissing('employee.branch');

        return [
            'id' => $salary->id,
            'employee_id' => $salary->employee_id,
            'employee_name' => $salary->employee?->name ?? '',
            'branch_name' => $salary->employee?->branch?->name ?? '',
            'period' => $salary->period,
            'base_salary' => (float) $salary->base_salary,
            'allowances' => (float) $salary->allowances,
            'deductions' => (float) $salary->deductions,
            'net_salary' => (float) $salary->net_salary,
            'status' => $salary->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function workshop(Workshop $workshop): array
    {
        return [
            'id' => $workshop->id,
            'workshop_code' => $workshop->workshop_code,
            'name' => $workshop->name,
            'city' => $workshop->city ?? '',
            'address' => $workshop->address ?? '',
            'inventory_name' => $workshop->inventory_name ?? '',
            'status' => $workshop->status,
            'created_at' => $workshop->created_at?->toDateString() ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function workshopTransfer(WorkshopTransfer $transfer): array
    {
        return [
            'id' => $transfer->id,
            'workshop_id' => $transfer->workshop_id,
            'transfer_code' => $transfer->transfer_code,
            'from_branch' => $transfer->from_branch ?? '',
            'to_workshop' => $transfer->to_workshop ?? '',
            'item_name' => $transfer->item_name,
            'quantity' => (int) $transfer->quantity,
            'status' => $transfer->status,
            'created_at' => $transfer->created_at?->toDateString() ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function workshopCloth(WorkshopCloth $cloth): array
    {
        return [
            'id' => $cloth->id,
            'workshop_id' => $cloth->workshop_id,
            'cloth_code' => $cloth->cloth_code ?? '',
            'customer_name' => $cloth->customer_name ?? '',
            'product_name' => $cloth->product_name ?? '',
            'workshop_status' => $cloth->workshop_status,
            'updated_at' => $cloth->updated_at?->toDateString() ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function factory(Factory $factory): array
    {
        return [
            'id' => $factory->id,
            'factory_code' => $factory->factory_code,
            'name' => $factory->name,
            'city' => $factory->city ?? '',
            'address' => $factory->address ?? '',
            'inventory_name' => $factory->inventory_name ?? '',
            'status' => $factory->status,
            'created_at' => $factory->created_at?->toDateString() ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function notification(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'category' => $notification->category,
            'priority' => $notification->priority,
            'read_at' => $notification->read_at?->toDateTimeString(),
            'created_at' => $notification->created_at?->toDateTimeString() ?? '',
            'action_url' => $notification->action_url,
        ];
    }
}
