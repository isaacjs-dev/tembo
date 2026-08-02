<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Http\Request;

class ExamApiController extends Controller
{
    public function index(Request $request)
    {
        $orgId = $request->user()->organization_id;

        $exams = Exam::where('organization_id', $orgId)
            ->where('author_id', $request->user()->id)
            ->where('status', 'published')
            ->withCount(['questions', 'submissions'])
            ->with('schoolClasses:id,name')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'status' => $exam->status,
                'questions_count' => $exam->questions_count,
                'submissions_count' => $exam->submissions_count,
                'classes' => $exam->schoolClasses->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ]),
                'updated_at' => $exam->updated_at->toISOString(),
            ]);

        return response()->json(['exams' => $exams]);
    }

    public function download(Request $request, Exam $exam)
    {
        $orgId = $request->user()->organization_id;

        abort_unless(
            (int) $exam->organization_id === (int) $orgId
            && (int) $exam->author_id === (int) $request->user()->id
            && $exam->status === 'published',
            404
        );

        $exam->load(['questions', 'schoolClasses']);

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

        // Students in the classes assigned to this exam
        $classIds = $exam->schoolClasses->pluck('id');
        $students = User::where('organization_id', $orgId)
            ->where('type', 'student')
            ->whereHas('schoolClasses', fn ($q) => $q->whereIn('school_classes.id', $classIds))
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
            ],
            'copies' => $copies,
            'questions' => $questions,
            'students' => $students,
            'downloaded_at' => now()->toISOString(),
        ]);
    }
}
