<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionJob extends Model
{
    protected $fillable = ['order_id', 'order_item_id', 'quantity', 'stage', 'expected_on', 'notes'];

    protected function casts(): array
    {
        return ['expected_on' => 'date'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(TailorDispatch::class);
    }
}
