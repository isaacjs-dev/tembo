<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\EventLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $organizationId = $request->user()->organization_id;
        abort_unless($organizationId, 403, 'Sem vínculo institucional.');

        $validated = $request->validate([
            'severity' => 'nullable|in:info,warning,error,critical',
            'search' => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $logs = EventLog::query()
            ->where('organization_id', $organizationId)
            ->with(['actor:id,name,type'])
            ->when($validated['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('event_code', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhere('entity_type', 'like', "%{$search}%");
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
