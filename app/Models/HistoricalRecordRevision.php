<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricalRecordRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'historical_import_record_id', 'previous_transcription', 'corrected_transcription', 'reason', 'corrected_by', 'corrected_at',
    ];

    protected function casts(): array
    {
        return ['corrected_at' => 'datetime'];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(HistoricalImportRecord::class, 'historical_import_record_id');
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
