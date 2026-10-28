<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialCorrection extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['old_amount' => 'decimal:2', 'new_amount' => 'decimal:2', 'occurred_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
