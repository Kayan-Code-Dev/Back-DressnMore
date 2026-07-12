<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Tools;

use JsonSerializable;

final class BusinessToolResult implements JsonSerializable
{
    public function __construct(
        public readonly string $tool,
        public readonly string $version,
        public readonly string $status,
        public readonly array $facts = [],
        public readonly array $scope = [],
        public readonly array $warnings = [],
        public readonly ?string $error = null,
        public readonly ?int $executionMs = null,
    ) {}

    public function isOk(): bool { return $this->status === 'ok'; }
    public function isEmpty(): bool { return $this->status === 'empty'; }
    public function isDenied(): bool { return $this->status === 'denied'; }
    public function isError(): bool { return $this->status === 'error'; }

    public function toTrustedFactsBlock(): string
    {
        $lines = ["=== {$this->tool} (v{$this->version}) ==="];
        foreach ($this->flattenFacts() as $key => $value) { $lines[] = "- {$key}: {$value}"; }
        if ($this->warnings !== []) { $lines[] = ''; $lines[] = 'Warnings:'; foreach ($this->warnings as $w) { $lines[] = "- {$w}"; } }
        return implode("\n", $lines);
    }

    private function flattenFacts(): array { $flat = []; $this->flatten('', $this->facts, $flat); return $flat; }

    private function flatten(string $prefix, mixed $value, array &$out): void
    {
        if (is_array($value) && !array_is_list($value)) { foreach ($value as $k => $v) { $this->flatten($prefix ? "{$prefix}.{$k}" : $k, $v, $out); } }
        elseif (is_array($value)) { foreach ($value as $i => $v) { $this->flatten($prefix ? "{$prefix}.[{$i}]" : "[{$i}]", $v, $out); } }
        else { $out[$prefix] = is_string($value) ? $value : json_encode($value); }
    }

    public static function denied(string $tool, array $missing): self
    {
        return new self(tool: $tool, version: '0.0.0', status: 'denied', facts: ['access' => 'denied'], warnings: ['Missing permissions: ' . implode(', ', $missing)]);
    }

    public static function error(string $tool, string $message, array $scope = []): self
    {
        return new self(tool: $tool, version: '0.0.0', status: 'error', facts: ['error' => $message], scope: $scope, error: $message);
    }

    public static function empty(string $tool, string $version, array $scope = []): self
    {
        return new self(tool: $tool, version: $version, status: 'empty', facts: ['result' => 'no data available for the requested period'], scope: $scope);
    }

    public function jsonSerialize(): array
    {
        return ['tool' => $this->tool, 'version' => $this->version, 'status' => $this->status, 'facts' => $this->facts, 'scope' => $this->scope, 'warnings' => $this->warnings, 'error' => $this->error, 'execution_ms' => $this->executionMs];
    }
}
