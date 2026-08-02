<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\LearningMaterial;
use App\Models\Question;
use App\Models\SchoolClass;
use Illuminate\Http\Request;

class TrashController extends Controller
{
    /**
     * Lista itens na lixeira (soft-deleted) da organização.
     */
    public function index()
    {
        $orgId = auth()->user()->organization_id;

        $trashedQuestions = Question::onlyTrashed()
            ->where('organization_id', $orgId)
            ->with('owner')
            ->latest('deleted_at')
            ->paginate(10, ['*'], 'questions_page');

        $trashedClasses = SchoolClass::onlyTrashed()
            ->where('organization_id', $orgId)
            ->latest('deleted_at')
            ->paginate(10, ['*'], 'classes_page');

        $trashedExams = Exam::withoutGlobalScopes()
            ->onlyTrashed()
            ->where('organization_id', $orgId)
            ->with('author:id,name')
            ->latest('deleted_at')
            ->paginate(10, ['*'], 'exams_page');

        $trashedMaterials = LearningMaterial::onlyTrashed()
            ->where('organization_id', $orgId)
            ->when(
                auth()->user()->type === 'teacher',
                fn ($query) => $query->where('author_id', auth()->id())
            )
            ->with('author:id,name')
            ->latest('deleted_at')
            ->paginate(10, ['*'], 'materials_page');

        return view('institution.trash.index', compact(
            'trashedQuestions',
            'trashedClasses',
            'trashedExams',
            'trashedMaterials',
        ));
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

        $orgId = auth()->user()->organization_id;

        $model = match ($validated['model_type']) {
            'question' => Question::onlyTrashed()->where('organization_id', $orgId)->findOrFail($validated['model_id']),
            'class' => SchoolClass::onlyTrashed()->where('organization_id', $orgId)->findOrFail($validated['model_id']),
            'exam' => Exam::withoutGlobalScopes()->onlyTrashed()->where('organization_id', $orgId)->findOrFail($validated['model_id']),
            'learning_material' => LearningMaterial::onlyTrashed()
                ->where('organization_id', $orgId)
                ->when(
                    auth()->user()->type === 'teacher',
                    fn ($query) => $query->where('author_id', auth()->id())
                )
                ->findOrFail($validated['model_id']),
            default => abort(400, 'Tipo inválido.'),
        };

        $model->restore();

        AuditLog::log('restored', get_class($model), $model->id);

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

        $orgId = auth()->user()->organization_id;

        $model = match ($validated['model_type']) {
            'question' => Question::onlyTrashed()->where('organization_id', $orgId)->findOrFail($validated['model_id']),
            'class' => SchoolClass::onlyTrashed()->where('organization_id', $orgId)->findOrFail($validated['model_id']),
            'exam' => Exam::withoutGlobalScopes()->onlyTrashed()->where('organization_id', $orgId)->findOrFail($validated['model_id']),
            'learning_material' => LearningMaterial::onlyTrashed()
                ->where('organization_id', $orgId)
                ->when(
                    auth()->user()->type === 'teacher',
                    fn ($query) => $query->where('author_id', auth()->id())
                )
                ->findOrFail($validated['model_id']),
            default => abort(400, 'Tipo inválido.'),
        };

        AuditLog::log('force_deleted', get_class($model), $model->id);

        $model->forceDelete();

        return back()->with('status', 'Item excluído permanentemente.');
    }
}
