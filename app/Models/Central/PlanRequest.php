<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlanRequest extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'request_type',
        'source_tenant_id',
        'plan_id',
        'old_plan_id',
        'name',
        'email',
        'phone',
        'password',
        'provision_password',
        'company_name',
        'payment_gateway_id',
        'payment_reference',
        'payment_proof_path',
        'payment_submitted_at',
        'status',
        'tenant_id',
        'subscription_id',
        'admin_notes',
        'tenant_notes',
        'billing_cycle',
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
            'payment_submitted_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function oldPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'old_plan_id');
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sourceTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'source_tenant_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}

