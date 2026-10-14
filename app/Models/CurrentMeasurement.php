<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrentMeasurement extends Model
{
    protected $fillable = [
        'child_id', 'shoulder_cm', 'chest_cm', 'sleeve_cm', 'waist_circumference_cm', 'length_cm', 'updated_by',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }
}
