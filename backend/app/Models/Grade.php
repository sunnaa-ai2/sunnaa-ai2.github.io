<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The trainer's private mark on one submission; only its owner and admins can read it. */
class Grade extends Model
{
    protected $primaryKey = 'work_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['work_id', 'score', 'comment', 'graded_by'];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }
}
