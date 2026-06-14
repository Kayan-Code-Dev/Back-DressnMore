<?php

namespace App\Services\Platform;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlanRequestPaymentProofService
{
    private const DISK = 'public';

    private const DIRECTORY = 'plan-requests/payment-proofs';

    public function store(UploadedFile $file, int $requestId): string
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $filename = $requestId.'_'.Str::uuid()->toString().'.'.$extension;

        Storage::disk(self::DISK)->putFileAs(self::DIRECTORY, $file, $filename);

        return self::DIRECTORY.'/'.$filename;
    }

    public function url(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->url($path);
    }
}
