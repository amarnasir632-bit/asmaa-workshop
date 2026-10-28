<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TailorReceipt extends Model
{
    protected $fillable = ['tailor_dispatch_id', 'received_on', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['received_on' => 'date'];
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(TailorDispatch::class, 'tailor_dispatch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TailorReceiptItem::class);
    }
}
