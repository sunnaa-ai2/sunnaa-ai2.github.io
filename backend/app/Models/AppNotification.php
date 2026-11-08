<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    use HasUuids;

    protected $table = 'app_notifications';
    protected $fillable = ['user_id', 'actor_id', 'kind', 'work_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
