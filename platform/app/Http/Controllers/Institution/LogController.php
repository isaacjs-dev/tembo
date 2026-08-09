<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $organizationId = $request->user()->organization_id;
        abort_unless($organizationId, 403, 'Sem vínculo institucional.');

        $validated = $request->validate([
            'severity' => 'nullable|in:info,warning,error,critical',
            'origin' => 'nullable|in:web,api,console,system',
            'actor_id' => 'nullable|integer',
            'search' => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $logs = AuditLog::query()
            ->where('organization_id', $organizationId)
            ->with(['user:id,name,type'])
            ->when($validated['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->when($validated['origin'] ?? null, fn ($query, $origin) => $query->where('origin', $origin))
            ->when($validated['actor_id'] ?? null, fn ($query, $actorId) => $query->where('user_id', $actorId))
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('action', 'like', "%{$search}%")
                        ->orWhere('model_type', 'like', "%{$search}%")
                        ->orWhere('request_id', $search);
                });
            })
            ->when($validated['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('institution.logs.index', compact('logs'));
    }
}
