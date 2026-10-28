<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Beneficiary extends Model
{
    protected $fillable = ['school_contract_id', 'child_id', 'name_snapshot', 'grade_snapshot', 'source_reference'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SchoolContract::class, 'school_contract_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
