<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'action' => 'nullable|string|max:100',
            'user_id' => 'nullable|integer|exists:users,id',
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'model_type' => 'nullable|string|max:150',
            'origin' => 'nullable|in:web,api,console,system',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);
        $query = AuditLog::with(['user', 'organization'])->orderByDesc('created_at');

        if ($validated['action'] ?? null) {
            $query->byAction($validated['action']);
        }
        if ($validated['user_id'] ?? null) {
            $query->byUser((int) $validated['user_id']);
        }
        if ($validated['organization_id'] ?? null) {
            $query->where('organization_id', $validated['organization_id']);
        }
        if ($validated['model_type'] ?? null) {
            $query->where('model_type', 'like', "%{$validated['model_type']}%");
        }
        if ($validated['origin'] ?? null) {
            $query->where('origin', $validated['origin']);
        }
        if (($validated['date_from'] ?? null) && ($validated['date_to'] ?? null)) {
            $query->inPeriod($validated['date_from'], $validated['date_to'].' 23:59:59');
        }

        $logs = $query->paginate(25)->withQueryString();

        // Valores para filtros
        $actions = AuditLog::select('action')->distinct()->orderBy('action')->pluck('action');

        return view('admin.audit-logs.index', compact('logs', 'actions'));
    }
}
