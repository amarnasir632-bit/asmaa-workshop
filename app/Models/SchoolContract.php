<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolContract extends Model
{
    protected $fillable = ['order_id', 'school_id', 'contract_number', 'contact_name', 'terms'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }
}
