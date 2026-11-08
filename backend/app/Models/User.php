<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $fillable = ['email', 'email_verified_at'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime'];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class, 'user_id');
    }

    public function isAdmin(): bool
    {
        return in_array(strtolower($this->email), config('spark.admins'), true);
    }

    public function isMember(): bool
    {
        return $this->profile()->exists();
    }
}
