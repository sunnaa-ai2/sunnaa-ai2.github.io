<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    protected $primaryKey = 'no';
    public $incrementing = false;
    protected $fillable = ['no', 'title', 'description'];

    protected function casts(): array
    {
        return ['no' => 'integer'];
    }
}
