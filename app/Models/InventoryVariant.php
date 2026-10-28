<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventoryVariant extends Model
{
    protected $fillable = [
        'school_id', 'garment_type_id', 'grade_id', 'size', 'color', 'sku', 'attributes_key', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function garmentType(): BelongsTo
    {
        return $this->belongsTo(GarmentType::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function balance(): HasOne
    {
        return $this->hasOne(InventoryBalance::class);
    }

    public function openingInventoryEntries(): HasMany
    {
        return $this->hasMany(OpeningInventoryEntry::class);
    }
}
