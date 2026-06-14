<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanRequest extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'plan_id',
        'name',
        'email',
        'phone',
        'password',
        'provision_password',
        'company_name',
        'payment_gateway_id',
        'status',
        'tenant_id',
        'subscription_id',
        'admin_notes',
        'approved_at',
        'approved_by',
    ];

    protected $hidden = [
        'password',
        'provision_password',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}

