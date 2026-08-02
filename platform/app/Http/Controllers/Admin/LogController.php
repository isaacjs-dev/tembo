<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventLog;

class LogController extends Controller
{
    public function index()
    {
        // Administrador Global vê todos os logs do sistema
        $logs = EventLog::with(['actor', 'organization'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.logs.index', compact('logs'));
    }
}
