<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricalRecordIssue extends Model
{
    protected $fillable = [
        'historical_import_batch_id', 'record_type', 'source_reference', 'issue_type', 'description', 'resolution', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(HistoricalImportBatch::class, 'historical_import_batch_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
