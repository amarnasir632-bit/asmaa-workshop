<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TailorReceiptItem extends Model
{
    protected $fillable = ['tailor_receipt_id', 'tailor_dispatch_item_id', 'quantity_received'];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(TailorReceipt::class, 'tailor_receipt_id');
    }

    public function dispatchItem(): BelongsTo
    {
        return $this->belongsTo(TailorDispatchItem::class, 'tailor_dispatch_item_id');
    }
}
