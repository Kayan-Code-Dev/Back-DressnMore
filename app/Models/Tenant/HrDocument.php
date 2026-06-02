<?php

namespace App\Models\Tenant;

use App\Enums\HrDocumentStatus;
use App\Enums\HrDocumentType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrDocument extends BaseTenantModel
{
    protected $fillable = [
        'employee_id',
        'document_type',
        'file_name',
        'file_path',
        'issue_date',
        'expiry_date',
        'status',
        'notes',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return list<string>
     */
    public static function documentTypes(): array
    {
        return HrDocumentType::values();
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return HrDocumentStatus::values();
    }
}
