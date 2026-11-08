<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reaction extends Model
{
    protected $fillable = ['work_id', 'user_id', 'value'];

    protected function casts(): array
    {
        return ['value' => 'integer'];
    }
}
