<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CourseMaterial extends Model
{
    use HasUuids;

    protected $fillable = ['position', 'title', 'body'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
