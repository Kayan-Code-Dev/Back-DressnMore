<?php

namespace App\Models\Central;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class SuperAdmin extends Authenticatable
{
    use HasApiTokens;

    protected $connection = 'central';

    protected $table = 'super_admins';

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
