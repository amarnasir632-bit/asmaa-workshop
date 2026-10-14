<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function record(string $action, Model|string $subject, ?int $subjectId, array $changedFields = [], ?string $reason = null): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => is_string($subject) ? $subject : $subject::class,
            'subject_id' => $subjectId,
            'before_values' => null,
            'after_values' => json_encode(['changed_fields' => array_values($changedFields)], JSON_THROW_ON_ERROR),
            'reason' => $reason,
            'request_id' => substr((string) Request::header('X-Request-ID', ''), 0, 80) ?: null,
            'occurred_at' => now('UTC'),
        ]);
    }
}
