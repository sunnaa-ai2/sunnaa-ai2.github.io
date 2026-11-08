<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Work extends Model
{
    use HasUuids;

    protected $fillable = ['id', 'user_id', 'exercise_no', 'kind', 'url', 'file_path', 'file_name', 'mime'];

    protected function casts(): array
    {
        return ['exercise_no' => 'integer'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'user_id', 'user_id');
    }
}
