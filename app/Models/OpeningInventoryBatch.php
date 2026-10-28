<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpeningInventoryBatch extends Model
{
    protected $fillable = ['reference', 'counted_on', 'status', 'created_by', 'approved_by', 'approved_at', 'notes'];

    protected function casts(): array
    {
        return ['counted_on' => 'date', 'approved_at' => 'datetime'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(OpeningInventoryEntry::class);
    }
}
