<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Invite extends Model
{
    use HasUuids;

    protected $primaryKey = 'token';
    protected $fillable = ['email', 'created_by', 'used_at', 'used_by'];

    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }
}
