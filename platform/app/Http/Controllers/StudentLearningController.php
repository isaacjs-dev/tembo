<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Discipline;
use App\Models\LearningMaterial;
use App\Models\LearningMaterialProgress;
use App\Models\User;
use App\Services\LearningRecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentLearningController extends Controller
{
    public function __construct(
        private readonly LearningRecommendationService $recommendations,
    ) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'discipline_id' => ['nullable', 'integer'],
        ]);
        $student = $request->user();
        $materials = $this->recommendations->forStudent($student, $validated);
        $disciplines = Discipline::query()
            ->where('organization_id', $student->organization_id)
            ->orderBy('name')
            ->get(['id', 'name']);
        $learningProgress = [
            'materials_opened' => LearningMaterialProgress::query()
                ->where('organization_id', $student->organization_id)
                ->where('student_id', $student->id)
                ->count(),
            'materials_completed' => LearningMaterialProgress::query()
                ->where('organization_id', $student->organization_id)
                ->where('student_id', $student->id)
                ->where('status', 'completed')
                ->count(),
            'recommended_now' => $materials->getCollection()
                ->where('recommendation_score', '>', 0)
                ->count(),
        ];

        return view('student.learning.index', compact('materials', 'disciplines', 'learningProgress'));
    }

    public function show(Request $request, LearningMaterial $learningMaterial): View
    {
        $student = $request->user();
        $this->ensureAvailableTo($learningMaterial, $student);
        $learningMaterial->load(['author:id,name', 'discipline:id,name', 'customSkill:id,name', 'bnccNode:id,code,title']);

        $progress = LearningMaterialProgress::query()->firstOrCreate(
            [
                'learning_material_id' => $learningMaterial->id,
                'student_id' => $student->id,
            ],
            [
                'organization_id' => $student->organization_id,
                'status' => 'opened',
                'view_count' => 1,
                'opened_at' => now(),
                'last_viewed_at' => now(),
            ]
        );

        if (! $progress->wasRecentlyCreated) {
            $progress->increment('view_count');
            $progress->update(['last_viewed_at' => now()]);
        }

        return view('student.learning.show', [
            'material' => $learningMaterial,
            'progress' => $progress,
        ]);
    }

    public function complete(Request $request, LearningMaterial $learningMaterial): RedirectResponse
    {
        $student = $request->user();
        $this->ensureAvailableTo($learningMaterial, $student);

        $progress = LearningMaterialProgress::query()->firstOrNew([
            'learning_material_id' => $learningMaterial->id,
            'student_id' => $student->id,
        ]);
        $wasCompleted = $progress->exists && $progress->status === 'completed';

        $progress->fill([
            'organization_id' => $student->organization_id,
            'status' => 'completed',
            'view_count' => max(1, (int) $progress->view_count),
            'opened_at' => $progress->opened_at ?: now(),
            'last_viewed_at' => now(),
            'completed_at' => $progress->completed_at ?: now(),
        ])->save();

        if (! $wasCompleted) {
            AuditLog::log(
                'learning_material_completed',
                LearningMaterial::class,
                $learningMaterial->id,
                [
                    'student_id' => $student->id,
                    'organization_id' => $student->organization_id,
                ]
            );
        }

        return back()->with('status', 'Revisão marcada como concluída.');
    }

    private function ensureAvailableTo(LearningMaterial $learningMaterial, User $student): void
    {
        $allowed = (int) $learningMaterial->organization_id === (int) $student->organization_id
            && $learningMaterial->status === 'published'
            && $learningMaterial->schoolClasses()
                ->whereHas('students', fn ($query) => $query->where('users.id', $student->id))
                ->exists();

        abort_unless($allowed, 404);
    }
}
