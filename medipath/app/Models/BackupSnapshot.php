<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupSnapshot extends Model
{
    protected $fillable = ['label', 'disk', 'path', 'size_bytes', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
