<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HistoricalImportRecord extends Model
{
    protected $fillable = [
        'historical_import_batch_id', 'record_type', 'source_reference', 'original_date', 'transcription',
        'fingerprint', 'confidence', 'review_status', 'entered_by', 'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected function casts(): array
    {
        return ['original_date' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(HistoricalImportBatch::class, 'historical_import_batch_id');
    }

    public function enterer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(HistoricalRecordRevision::class);
    }
}
