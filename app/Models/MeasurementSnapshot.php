<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementSnapshot extends Model
{
    protected $fillable = ['shoulder_cm', 'chest_cm', 'sleeve_cm', 'waist_circumference_cm', 'length_cm'];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
