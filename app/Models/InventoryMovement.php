<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'inventory_variant_id', 'quantity_change', 'movement_type', 'order_item_id', 'delivery_item_id',
        'opening_inventory_entry_id', 'inventory_adjustment_item_id', 'reason', 'occurred_at', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(InventoryVariant::class, 'inventory_variant_id');
    }
}
