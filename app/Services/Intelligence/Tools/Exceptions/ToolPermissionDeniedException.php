<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools\Exceptions;

use RuntimeException;

class ToolPermissionDeniedException extends RuntimeException
{
    public function __construct(
        public readonly string $tool,
        public readonly array $missing,
    ) {
        parent::__construct(sprintf(
            'Tool "%s" requires permissions: %s',
            $tool,
            implode(', ', $missing),
        ));
    }
}
