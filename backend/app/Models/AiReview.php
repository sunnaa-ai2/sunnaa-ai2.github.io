<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiReview extends Model
{
    use HasUuids;

    protected $fillable = ['work_id', 'user_id', 'body', 'model'];
}
