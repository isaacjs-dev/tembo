<?php

namespace App\Http\Controllers;

use App\Models\EventLog;
use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Question;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\MonthlyUsageService;
use Illuminate\Http\Request;

class InstitutionController extends Controller
{
    public function dashboard(Request $request, MonthlyUsageService $usage)
    {
        $orgId = auth()->user()->organization_id;

        $stats = [
            'students_count' => User::where('organization_id', $orgId)->where('type', 'student')->count(),
            'teachers_count' => User::where('organization_id', $orgId)->where('type', 'teacher')->count(),
            'classes_count' => SchoolClass::where('organization_id', $orgId)->count(),
            'exams_count' => Exam::where('organization_id', $orgId)->count(),
            'submissions_count' => ExamSubmission::whereHas('exam', function ($q) use ($orgId) {
                $q->where('organization_id', $orgId);
            })->count(),
        ];

        $recentActivities = ExamSubmission::with(['user', 'exam'])
            ->whereHas('exam', function ($q) use ($orgId) {
                $q->where('organization_id', $orgId);
            })
            ->latest()
            ->take(5)
            ->get();

        $personalUsage = null;
        if ($request->user()->type === 'teacher') {
            $personalUsage = collect(MonthlyUsageService::RESOURCES)->mapWithKeys(fn (string $resource) => [
                $resource => $usage->snapshot($request->user(), $resource),
            ]);
        }

        return view('institution.dashboard', compact('stats', 'recentActivities', 'personalUsage'));
    }

    public function settings()
    {
        $organization = auth()->user()->organization;

        return view('institution.settings', compact('organization'));
    }

    public function updateSettings(Request $request)
    {
        $organization = auth()->user()->organization;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subdomain' => 'nullable|string|max:255|unique:organizations,subdomain,'.$organization->id,
            'primary_color' => 'nullable|string|max:7',
        ]);

        $settings = $organization->settings ?? [];
        if ($request->has('primary_color')) {
            $settings['primary_color'] = $validated['primary_color'];
        }

        $organization->update([
            'name' => $validated['name'],
            'subdomain' => $validated['subdomain'],
            'settings' => $settings,
        ]);

        EventLog::create([
            'organization_id' => $organization->id,
            'actor_user_id' => auth()->id(),
            'event_code' => 'institution.settings.updated',
            'entity_type' => 'organizations',
            'entity_id' => $organization->id,
            'message' => 'Configurações da instituição atualizadas',
        ]);

        return redirect()->route('institution.settings')->with('status', 'Configurações salvas!');
    }

    public function trash()
    {
        $organization_id = auth()->user()->organization_id;

        $deletedQuestions = Question::onlyTrashed()->where('organization_id', $organization_id)->get();
        $deletedUsers = User::onlyTrashed()->where('organization_id', $organization_id)->get();
        $deletedClasses = SchoolClass::onlyTrashed()->where('organization_id', $organization_id)->get();

        return view('institution.trash', compact('deletedQuestions', 'deletedUsers', 'deletedClasses'));
    }

    public function restoreTrash(Request $request, string $type, string $id)
    {
        $organization_id = auth()->user()->organization_id;

        if ($type === 'question') {
            $record = Question::onlyTrashed()->where('organization_id', $organization_id)->findOrFail($id);
        } elseif ($type === 'user') {
            $record = User::onlyTrashed()->where('organization_id', $organization_id)->findOrFail($id);
        } elseif ($type === 'class') {
            $record = SchoolClass::onlyTrashed()->where('organization_id', $organization_id)->findOrFail($id);
        } else {
            abort(404);
        }

        $record->restore();

        EventLog::create([
            'organization_id' => $organization_id,
            'actor_user_id' => auth()->id(),
            'event_code' => "{$type}.restored",
            'entity_type' => $type,
            'entity_id' => $id,
            'message' => 'Registro restaurado da lixeira',
        ]);

        return redirect()->route('institution.trash.index')->with('status', 'Registro restaurado com sucesso!');
    }

    public function forceDeleteTrash(Request $request, string $type, string $id)
    {
        $organization_id = auth()->user()->organization_id;

        if ($type === 'question') {
            $record = Question::onlyTrashed()->where('organization_id', $organization_id)->findOrFail($id);
        } elseif ($type === 'user') {
            $record = User::onlyTrashed()->where('organization_id', $organization_id)->findOrFail($id);
        } elseif ($type === 'class') {
            $record = SchoolClass::onlyTrashed()->where('organization_id', $organization_id)->findOrFail($id);
        } else {
            abort(404);
        }

        $record->forceDelete();

        EventLog::create([
            'organization_id' => $organization_id,
            'actor_user_id' => auth()->id(),
            'event_code' => "{$type}.force_deleted",
            'entity_type' => $type,
            'entity_id' => $id,
            'message' => 'Registro excluído permanentemente',
            'severity' => 'warning',
        ]);

        return redirect()->route('institution.trash.index')->with('status', 'Registro excluído permanentemente!');
    }
}
