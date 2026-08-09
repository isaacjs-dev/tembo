<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\WorkspaceContextService;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $type = app(WorkspaceContextService::class)->roleFor($user);

        if ($type === null) {
            return redirect()->route('workspaces.index');
        }

        if ($type === 'student') {
            return redirect()->route('student.dashboard');
        } elseif ($type === 'guardian') {
            return redirect()->route('guardian.dashboard');
        } elseif ($type === 'global_admin') {
            return redirect()->route('admin.dashboard');
        } elseif (in_array($type, ['admin', 'institution_admin'], true)) {
            return redirect()->route('institution.dashboard');
        }

        // $type === 'teacher'
        $orgId = $user->organization_id;

        $stats = [
            'my_exams_count' => Exam::where('author_id', $user->id)
                ->where('organization_id', $orgId)->count(),

            'my_classes_count' => SchoolClass::where('organization_id', $orgId)
                ->where(function ($q) use ($user) {
                    $q->where(function ($q2) use ($user) {
                        $q2->where('owner_type', User::class)->where('owner_id', $user->id);
                    })->orWhereHas('teachers', fn ($q2) => $q2->where('users.id', $user->id));
                })->count(),

            'submissions_to_grade' => ExamSubmission::whereHas('exam', function ($q) use ($user, $orgId) {
                $q->where('author_id', $user->id)->where('organization_id', $orgId);
            })->where('status', 'submitted')->count(),
        ];

        $recentExams = Exam::where('author_id', $user->id)
            ->where('organization_id', $orgId)
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard', compact('stats', 'recentExams'));
    }
}
