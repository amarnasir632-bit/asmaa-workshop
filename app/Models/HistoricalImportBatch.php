<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HistoricalImportBatch extends Model
{
    protected $fillable = ['source_description', 'imported_on', 'imported_by', 'records_seen', 'records_imported', 'records_flagged', 'notes'];

    protected function casts(): array
    {
        return ['imported_on' => 'date'];
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(HistoricalImportRecord::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(HistoricalRecordIssue::class);
    }
}
