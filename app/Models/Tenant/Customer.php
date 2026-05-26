<?php

namespace App\Models\Tenant;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends BaseTenantModel
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'date_of_birth',
        'phone',
        'phone2',
        'whatsapp',
        'email',
        'address',
        'city_id',
        'national_id',
        'source',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return CustomerStatus::values();
    }

    protected function phone2(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value,
            set: fn (?string $value): ?string => $value !== null ? trim($value) : null,
        );
    }
}
