<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;

class TrashController extends Controller
{
    /**
     * Lista itens na lixeira (soft-deleted) global.
     */
    public function index()
    {
        $trashedUsers = User::onlyTrashed()
            ->latest('deleted_at')
            ->paginate(10, ['*'], 'users_page');

        $trashedPlans = Plan::onlyTrashed()
            ->latest('deleted_at')
            ->paginate(10, ['*'], 'plans_page');

        return view('admin.trash.index', compact('trashedUsers', 'trashedPlans'));
    }

    /**
     * Restaurar um item da lixeira.
     */
    public function restore(Request $request)
    {
        $validated = $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
        ]);

        $model = match ($validated['model_type']) {
            'user' => User::onlyTrashed()->findOrFail($validated['model_id']),
            'plan' => Plan::onlyTrashed()->findOrFail($validated['model_id']),
            default => abort(400, 'Tipo inválido.'),
        };

        $model->restore();

        // Registrar no AuditLog, se houver
        if (class_exists(AuditLog::class)) {
            AuditLog::log('restored', get_class($model), $model->id);
        }

        return back()->with('status', 'Item restaurado com sucesso!');
    }

    /**
     * Excluir permanentemente da lixeira.
     */
    public function forceDelete(Request $request)
    {
        $validated = $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
        ]);

        $model = match ($validated['model_type']) {
            'user' => User::onlyTrashed()->findOrFail($validated['model_id']),
            'plan' => Plan::onlyTrashed()->findOrFail($validated['model_id']),
            default => abort(400, 'Tipo inválido.'),
        };

        if (class_exists(AuditLog::class)) {
            AuditLog::log('force_deleted', get_class($model), $model->id);
        }

        $model->forceDelete();

        return back()->with('status', 'Item excluído permanentemente.');
    }
}
