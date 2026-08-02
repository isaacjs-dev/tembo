<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')->orderByDesc('created_at');

        // Filtros
        if ($request->filled('action')) {
            $query->byAction($request->action);
        }
        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }
        if ($request->filled('model_type')) {
            $query->where('model_type', 'like', "%{$request->model_type}%");
        }
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->inPeriod($request->date_from, $request->date_to.' 23:59:59');
        }

        $logs = $query->paginate(25)->withQueryString();

        // Valores para filtros
        $actions = AuditLog::select('action')->distinct()->orderBy('action')->pluck('action');

        return view('admin.audit-logs.index', compact('logs', 'actions'));
    }
}
