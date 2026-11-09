<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Work extends Model
{
    use HasUuids;

    /** kind: "files" (1 to 8 uploads in work_files) or "link" (url). */
    protected $fillable = ['id', 'user_id', 'exercise_no', 'kind', 'url'];

    protected function casts(): array
    {
        return ['exercise_no' => 'integer'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'user_id', 'user_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(WorkFile::class)->orderBy('position');
    }
}
