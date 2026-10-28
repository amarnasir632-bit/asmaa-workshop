<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('audit.view');
        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $logs = AuditLog::query()->with('user')->when($data['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($data['from'] ?? null, fn ($query, $from) => $query->whereDate('occurred_at', '>=', $from))
            ->when($data['to'] ?? null, fn ($query, $to) => $query->whereDate('occurred_at', '<=', $to))
            ->latest('occurred_at')->paginate(50)->withQueryString();
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        return view('audit.index', compact('logs', 'actions', 'data'));
    }
}
