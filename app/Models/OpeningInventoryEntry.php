<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningInventoryEntry extends Model
{
    protected $fillable = ['opening_inventory_batch_id', 'inventory_variant_id', 'quantity_counted', 'previous_quantity', 'difference_reason'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OpeningInventoryBatch::class, 'opening_inventory_batch_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(InventoryVariant::class, 'inventory_variant_id');
    }
}
