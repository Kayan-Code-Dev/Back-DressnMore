<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Branch;
use App\Models\Tenant\HrDepartment;
use App\Models\Tenant\HrEmployee;
use App\Models\Tenant\HrJobTitle;
use Illuminate\Database\Seeder;

class HrDemoSeeder extends Seeder
{
    public function run(): void
    {
        $departments = collect([
            'Sales',
            'Tailoring',
            'Accounting',
            'Operations',
            'Reception',
        ])->mapWithKeys(function (string $name): array {
            $department = HrDepartment::query()->firstOrCreate(
                ['name' => $name],
                ['status' => 'active'],
            );

            return [$name => $department];
        });

        $jobTitles = [
            ['title' => 'Branch Manager', 'department' => 'Operations'],
            ['title' => 'Sales Employee', 'department' => 'Sales'],
            ['title' => 'Tailor', 'department' => 'Tailoring'],
            ['title' => 'Accountant', 'department' => 'Accounting'],
            ['title' => 'Delivery Coordinator', 'department' => 'Operations'],
            ['title' => 'Receptionist', 'department' => 'Reception'],
        ];

        $titleModels = [];
        foreach ($jobTitles as $jobTitle) {
            $titleModels[$jobTitle['title']] = HrJobTitle::query()->firstOrCreate(
                ['title' => $jobTitle['title']],
                [
                    'department_id' => $departments[$jobTitle['department']]->id,
                    'status' => 'active',
                ],
            );
        }

        $branch = Branch::query()->first();

        $employees = [
            ['code' => 'EMP-D001', 'name' => 'Sara Al-Harbi', 'title' => 'Branch Manager', 'dept' => 'Operations', 'salary' => 9000],
            ['code' => 'EMP-D002', 'name' => 'Reem Al-Otaibi', 'title' => 'Sales Employee', 'dept' => 'Sales', 'salary' => 6500],
            ['code' => 'EMP-D003', 'name' => 'Noura Al-Qahtani', 'title' => 'Tailor', 'dept' => 'Tailoring', 'salary' => 5500],
            ['code' => 'EMP-D004', 'name' => 'Fatima Al-Zahrani', 'title' => 'Accountant', 'dept' => 'Accounting', 'salary' => 7200],
            ['code' => 'EMP-D005', 'name' => 'Maha Al-Ghamdi', 'title' => 'Receptionist', 'dept' => 'Reception', 'salary' => 4800],
        ];

        foreach ($employees as $index => $employee) {
            HrEmployee::query()->firstOrCreate(
                ['employee_code' => $employee['code']],
                [
                    'full_name' => $employee['name'],
                    'phone' => '+96650000000'.($index + 1),
                    'email' => strtolower(str_replace(' ', '.', $employee['name'])).'@demo.test',
                    'branch_id' => $branch?->id,
                    'department_id' => $departments[$employee['dept']]->id,
                    'job_title_id' => $titleModels[$employee['title']]->id,
                    'employment_type' => 'full_time',
                    'status' => 'active',
                    'joining_date' => now()->subYears(2)->addMonths($index)->toDateString(),
                    'base_salary' => $employee['salary'],
                    'salary_type' => 'monthly',
                    'working_hours_per_day' => 8,
                ],
            );
        }
    }
}
