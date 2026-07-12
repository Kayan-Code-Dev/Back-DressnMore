<?php

declare(strict_types=1);

namespace App\Models\Tenant\Intelligence;

use App\Models\Tenant\BaseTenantModel;

class AiToolExecution extends BaseTenantModel
{
    protected $fillable = [
        'tool_name', 'tool_version', 'status', 'facts', 'scope',
        'warnings', 'error', 'execution_ms', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'facts' => 'array', 'scope' => 'array', 'warnings' => 'array',
            'execution_ms' => 'integer', 'executed_at' => 'datetime',
        ];
    }

    public function isOk(): bool { return $this->status === 'ok'; }
    public function isDenied(): bool { return $this->status === 'denied'; }
    public function isError(): bool { return $this->status === 'error'; }
}
