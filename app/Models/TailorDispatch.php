<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TailorDispatch extends Model
{
    protected $fillable = ['production_job_id', 'recipient_name', 'sent_on', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['sent_on' => 'date'];
    }

    public function productionJob(): BelongsTo
    {
        return $this->belongsTo(ProductionJob::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TailorDispatchItem::class);
    }
}
