<?php

namespace App\Http\Controllers;

use App\Models\ExamSubmission;
use App\Models\User;
use App\Services\ExamAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuardianPortalController extends Controller
{
    public function __construct(private readonly ExamAccessService $examAccess) {}

    public function index(Request $request): View
    {
        $guardian = $request->user();
        $students = $guardian->guardedStudents()
            ->wherePivot('organization_id', $guardian->organization_id)
            ->whereIn('users.id', User::query()
                ->memberOfOrganization((int) $guardian->organization_id, 'student')
                ->select('users.id'))
            ->with('schoolClasses:id,name,year')
            ->orderBy('users.name')
            ->get();

        $studentIds = $students->pluck('id');
        $latestSubmissions = ExamSubmission::query()
            ->whereIn('user_id', $studentIds)
            ->whereIn('status', ['submitted', 'graded'])
            ->whereHas('exam', fn ($query) => $query
                ->where('organization_id', $guardian->organization_id)
                ->whereIn('status', ['published', 'closed']))
            ->with('exam:id,organization_id,title,status,settings')
            ->latest('finished_at')
            ->latest('id')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $this->visibleSubmission($rows->first()));

        return view('guardian.dashboard', compact('students', 'latestSubmissions'));
    }

    public function show(Request $request, User $student): View
    {
        $guardian = $request->user();
        $this->ensureLinked($guardian, $student);

        $student->load('schoolClasses:id,name,year');
        $submissions = ExamSubmission::query()
            ->where('user_id', $student->id)
            ->whereIn('status', ['submitted', 'graded'])
            ->whereHas('exam', fn ($query) => $query
                ->where('organization_id', $guardian->organization_id)
                ->whereIn('status', ['published', 'closed']))
            ->with('exam:id,organization_id,title,status,settings')
            ->latest('finished_at')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (ExamSubmission $submission) => $this->visibleSubmission($submission));

        return view('guardian.student', compact('student', 'submissions'));
    }

    private function ensureLinked(User $guardian, User $student): void
    {
        $linked = $guardian->guardedStudents()
            ->where('users.id', $student->id)
            ->wherePivot('organization_id', $guardian->organization_id)
            ->exists();

        if (! $linked || ! $student->belongsToActiveOrganization((int) $guardian->organization_id, 'student')) {
            throw new AuthorizationException(
                'Você não possui autorização para acompanhar este estudante.'
            );
        }
    }

    /**
     * A área do responsável obedece exatamente à janela e às opções de
     * liberação definidas pelo professor. O gabarito nunca é exposto aqui.
     *
     * @return array<string, mixed>
     */
    private function visibleSubmission(ExamSubmission $submission): array
    {
        $release = $this->examAccess->releaseSettings($submission->exam);

        return [
            'id' => $submission->id,
            'exam' => $submission->exam,
            'status' => $submission->status,
            'finished_at' => $submission->finished_at,
            'attempt_number' => $submission->attempt_number,
            'score' => $release['show_score'] && $submission->score !== null
                ? (float) $submission->score
                : null,
            'feedback' => $release['show_feedback']
                ? $submission->feedback
                : null,
            'results_released' => $release['show_score'] || $release['show_feedback'],
            'results_available_at' => $this->examAccess->resultsAvailableAt($submission->exam),
        ];
    }
}
