<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Refund extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['approved_amount' => 'decimal:2', 'refunded_amount' => 'decimal:2', 'approved_at' => 'datetime', 'refunded_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function originalPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'original_payment_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(RefundTransaction::class);
    }

    public function cancellation(): BelongsTo
    {
        return $this->belongsTo(Cancellation::class);
    }
}
