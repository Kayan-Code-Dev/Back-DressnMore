<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\ExpenseCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * @return list<array{name: string, slug: string, description: string}>
     */
    public static function defaults(): array
    {
        return [
            ['name' => 'إيجار ومرافق', 'slug' => 'rent-utilities', 'description' => 'إيجار المحل والكهرباء والمياه والإنترنت'],
            ['name' => 'رواتب وأجور', 'slug' => 'salaries', 'description' => 'رواتب الموظفين والمكافآت'],
            ['name' => 'مستلزمات تشغيل', 'slug' => 'supplies', 'description' => 'أقمشة وخامات ومستلزمات يومية'],
            ['name' => 'صيانة', 'slug' => 'maintenance', 'description' => 'صيانة الأجهزة والمعدات'],
            ['name' => 'تسويق وإعلان', 'slug' => 'marketing', 'description' => 'حملات إعلانية وسوشيال ميديا'],
            ['name' => 'مواصلات وشحن', 'slug' => 'transport', 'description' => 'توصيل وشحن ومواصلات'],
            ['name' => 'مصروفات إدارية', 'slug' => 'admin', 'description' => 'قرطاسية ومصاريف مكتبية'],
            ['name' => 'أخرى', 'slug' => 'other', 'description' => 'مصروفات متنوعة'],
        ];
    }

    public function run(): void
    {
        foreach (self::defaults() as $category) {
            ExpenseCategory::query()->firstOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'status' => ExpenseCategory::STATUS_ACTIVE,
                ],
            );
        }
    }
}
