<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TailorDispatchItem extends Model
{
    protected $fillable = ['tailor_dispatch_id', 'order_item_id', 'quantity_sent', 'agreed_cost'];

    protected function casts(): array
    {
        return ['agreed_cost' => 'decimal:2'];
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(TailorDispatch::class, 'tailor_dispatch_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(TailorReceiptItem::class);
    }
}
