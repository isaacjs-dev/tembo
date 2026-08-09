<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\User;
use App\Services\ExamAudienceService;
use Illuminate\Http\Request;

class ExamApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $orgId = $user->organization_id;
        $canReadInstitutionExams = $user->hasWorkspaceRole('admin', 'institution_admin', 'global_admin');

        $exams = Exam::where('organization_id', $orgId)
            // A printed card remains valid after the assessment is closed.
            // Drafts are never exposed to the mobile scanner.
            ->whereIn('status', ['published', 'closed'])
            ->when(! $canReadInstitutionExams, fn ($query) => $query->where('author_id', $user->id))
            ->withCount(['questions', 'submissions'])
            ->with(['discipline:id,name', 'schoolClasses:id,name'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'status' => $exam->status,
                'questions_count' => $exam->questions_count,
                'submissions_count' => $exam->submissions_count,
                'discipline' => $exam->discipline ? [
                    'id' => $exam->discipline->id,
                    'name' => $exam->discipline->name,
                ] : null,
                'classes' => $exam->schoolClasses->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ]),
                'updated_at' => $exam->updated_at->toISOString(),
            ]);

        return response()->json(['exams' => $exams]);
    }

    public function download(Request $request, Exam $exam, ExamAudienceService $audiences)
    {
        $user = $request->user();
        $orgId = $user->organization_id;
        $canReadInstitutionExams = $user->hasWorkspaceRole('admin', 'institution_admin', 'global_admin');

        abort_unless(
            (int) $exam->organization_id === (int) $orgId
            && ($canReadInstitutionExams || (int) $exam->author_id === (int) $user->id)
            && in_array($exam->status, ['published', 'closed'], true),
            404
        );

        $exam->load(['discipline:id,name', 'questions', 'schoolClasses', 'students']);

        // Get all copies for this exam
        $copies = $exam->copies()->get()->map(fn ($copy) => [
            'id' => $copy->id,
            'copy_number' => $copy->copy_number,
            'validation_hash' => $copy->validation_hash,
            'questions_map' => $copy->questions_map,
            'options_map' => $copy->options_map,
        ]);

        // Questions with answer key data
        $questions = $exam->questions->map(fn ($q) => [
            'id' => $q->id,
            'type' => $q->type,
            'correct_option' => $q->content['correct_option'] ?? null,
            'option_count' => isset($q->content['options']) ? count($q->content['options']) : 0,
            'points' => $q->pivot->points ?? 1,
            'order' => $q->pivot->order ?? 0,
        ]);

        // Direct and class audiences share one deduplicated offline contract.
        $students = User::query()
            ->memberOfOrganization((int) $orgId, 'student')
            ->whereIn('users.id', $audiences->studentIds($exam))
            ->with('studentProfile:id,user_id,registration_number')
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'registration_number' => $s->studentProfile?->registration_number,
            ]);

        return response()->json([
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'status' => $exam->status,
                'settings' => $exam->settings,
                'discipline' => $exam->discipline ? [
                    'id' => $exam->discipline->id,
                    'name' => $exam->discipline->name,
                ] : null,
            ],
            'copies' => $copies,
            'questions' => $questions,
            'students' => $students,
            'downloaded_at' => now()->toISOString(),
        ]);
    }
}
