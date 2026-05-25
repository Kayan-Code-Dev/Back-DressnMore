<?php

namespace App\Services\Auth;

use App\Models\Central\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PlatformAuthService
{
    public function login(string $email, string $password): array
    {
        $admin = SuperAdmin::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->first();

        if ($admin === null || ! Hash::check($password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (! $admin->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive.'],
            ]);
        }

        return [
            'token' => $admin->createToken('platform-token')->plainTextToken,
            'admin' => $admin,
        ];
    }
}
