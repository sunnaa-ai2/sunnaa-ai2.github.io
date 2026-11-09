<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkFile extends Model
{
    use HasUuids;

    protected $fillable = ['work_id', 'position', 'kind', 'path', 'name', 'mime', 'size'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'size' => 'integer'];
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }
}
