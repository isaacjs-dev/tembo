<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Discipline;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamSubmission;
use App\Models\KnowledgeArea;
use App\Models\OmrTemplate;
use App\Models\Question;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\AnswerSheetGeneratorService;
use App\Services\ConfigPrecedenceResolver;
use App\Services\ExamPrintService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExamController extends Controller
{
    public function index()
    {
        $exams = Exam::where('organization_id', auth()->user()->organization_id)
            ->where('author_id', auth()->id())
            ->withCount('questions')
            ->latest()
            ->paginate(10);

        return view('exams.index', compact('exams'));
    }

    public function create()
    {
        return view('exams.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'required|in:draft,published,closed',
            ...$this->settingsValidationRules(),
        ]);

        $settings = $this->settingsFromRequest($request);

        $exam = Exam::create([
            'organization_id' => auth()->user()->organization_id,
            'author_id' => auth()->id(),
            'title' => $validated['title'],
            // A prova ainda não tem itens neste passo; publicação acontece no editor.
            'status' => 'draft',
            'settings' => $settings,
        ]);

        AuditLog::log('exam_created', Exam::class, $exam->id, [
            'title' => $exam->title,
            'application_mode' => $settings['application_mode'],
        ]);

        return redirect()->route('exams.edit', $exam->id)
            ->with('status', 'Avaliação criada como rascunho. Adicione questões e revise as regras antes de publicar.');
    }

    public function show(string $id)
    {
        $exam = Exam::where('organization_id', auth()->user()->organization_id)
            ->where('author_id', auth()->id())
            ->with(['schoolClasses.students', 'questions'])
            ->findOrFail($id);

        $submissions = ExamSubmission::where('exam_id', $exam->id)
            ->with('user')
            ->get()
            ->keyBy('user_id');

        $printSettings = $this->getPrintSettings(auth()->user());

        // Templates de cartão-resposta visíveis ao usuário (padrão + da org + próprios).
        $cardTemplates = OmrTemplate::visible()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('exams.show', compact('exam', 'submissions', 'printSettings', 'cardTemplates'));
    }

    public function edit(string $id)
    {
        $organization_id = auth()->user()->organization_id;
        $user_id = auth()->id();

        $exam = Exam::where('organization_id', $organization_id)
            ->where('author_id', $user_id)
            ->with(['questions.discipline', 'questions.knowledgeArea', 'schoolClasses'])
            ->findOrFail($id);

        $availableClasses = SchoolClass::where('organization_id', $organization_id)->get();
        $disciplines = Discipline::with('knowledgeArea')->orderBy('name')->get();
        $knowledgeAreas = KnowledgeArea::orderBy('name')->get();

        $printSettings = $this->getPrintSettings(auth()->user());

        return view('exams.edit', compact('exam', 'availableClasses', 'disciplines', 'knowledgeAreas', 'printSettings'));
    }

    public function update(Request $request, string $id)
    {
        $exam = Exam::where('organization_id', auth()->user()->organization_id)
            ->where('author_id', auth()->id())
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'required|in:draft,published,closed',
            ...$this->settingsValidationRules(),
        ]);

        $previousStatus = $exam->status;
        $previousSettings = $exam->settings ?? [];
        $shouldUpdateSettings = $request->boolean('settings_form')
            || $request->hasAny(array_keys($this->settingsValidationRules()));
        $settings = $shouldUpdateSettings
            ? $this->settingsFromRequest($request, $previousSettings)
            : $previousSettings;

        if ($validated['status'] === 'published') {
            $this->ensurePublishable($exam, $settings);
        }

        $updateData = [
            'title' => $validated['title'],
            'status' => $validated['status'],
            'settings' => $settings,
        ];

        if ($validated['status'] === 'published' && empty($exam->access_code)) {
            $updateData['access_code'] = $this->generateAccessCode($exam->organization_id);
        }

        $exam->update($updateData);

        AuditLog::log('exam_updated', Exam::class, $exam->id, [
            'status' => ['old' => $previousStatus, 'new' => $exam->status],
            'settings_changed' => $previousSettings !== $settings,
        ]);

        return redirect()->route('exams.edit', $exam->id)->with('status', 'Configurações atualizadas!');
    }

    public function addQuestion(Request $request, string $id)
    {
        $exam = Exam::where('organization_id', auth()->user()->organization_id)
            ->where('author_id', auth()->id())
            ->findOrFail($id);

        $validated = $request->validate([
            'question_id' => 'required|integer',
            'points' => 'required|numeric|min:0',
        ]);

        $question = Question::whereKey($validated['question_id'])
            ->where('organization_id', $exam->organization_id)
            ->where(function ($query) {
                $query->where('owner_id', auth()->id())
                    ->orWhere('visibility_scope', 'org_public')
                    ->orWhereHas('shares', fn ($share) => $share->where('shared_with_user_id', auth()->id()));
            })
            ->firstOrFail();

        DB::transaction(function () use ($exam, $question, $validated) {
            if ($exam->questions()->where('questions.id', $question->id)->exists()) {
                return;
            }

            $order = ($exam->questions()->max('exam_questions.order') ?? 0) + 1;
            $exam->questions()->attach($question->id, [
                'points' => $validated['points'],
                'order' => $order,
            ]);
        });

        return redirect()->route('exams.edit', $exam->id)->with('status', 'Questão adicionada!');
    }

    public function removeQuestion(Request $request, string $id, string $question_id)
    {
        $exam = Exam::where('organization_id', auth()->user()->organization_id)
            ->where('author_id', auth()->id())
            ->findOrFail($id);

        $exam->questions()->detach($question_id);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('exams.edit', $exam->id)->with('status', 'Questão removida!');
    }

    public function syncClasses(Request $request, string $id)
    {
        $exam = Exam::where('organization_id', auth()->user()->organization_id)
            ->where('author_id', auth()->id())
            ->findOrFail($id);

        $validated = $request->validate([
            'class_ids' => 'nullable|array',
            'class_ids.*' => 'integer|distinct',
        ]);

        $classIds = collect($validated['class_ids'] ?? [])->map(fn ($classId) => (int) $classId)->values();
        $allowedClassIds = SchoolClass::withoutGlobalScopes()
            ->where('organization_id', $exam->organization_id)
            ->whereIn('id', $classIds)
            ->pluck('id')
            ->map(fn ($classId) => (int) $classId);

        if ($allowedClassIds->count() !== $classIds->count()) {
            throw ValidationException::withMessages([
                'class_ids' => 'Uma ou mais turmas não pertencem à instituição desta avaliação.',
            ]);
        }

        $exam->schoolClasses()->sync($allowedClassIds->all());

        return redirect()->route('exams.edit', $exam->id)->with('status', 'Turmas atualizadas!');
    }

    public function searchQuestions(Request $request, string $id)
    {
        $organization_id = auth()->user()->organization_id;
        $user_id = auth()->id();
        $tab = $request->input('tab', 'mine');

        $exam = Exam::where('organization_id', $organization_id)
            ->where('author_id', $user_id)
            ->findOrFail($id);

        $examQuestionIds = $exam->questions()->pluck('questions.id')->toArray();

        $query = Question::where('organization_id', $organization_id)
            ->with(['discipline', 'knowledgeArea', 'owner']);

        if ($tab === 'shared') {
            $query->where('owner_id', '!=', $user_id)
                ->whereHas('shares', fn ($sq) => $sq->where('shared_with_user_id', $user_id));
        } elseif ($tab === 'public') {
            $query->where('owner_id', '!=', $user_id)
                ->where('visibility_scope', 'org_public');
        } else {
            $query->where('owner_id', $user_id);
        }

        if ($search = $request->input('search')) {
            $query->where('content', 'like', '%'.$search.'%');
        }
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($level = $request->input('level')) {
            $query->where('level', $level);
        }
        if ($discipline_id = $request->input('discipline_id')) {
            $query->where('discipline_id', $discipline_id);
        }
        if ($stage = $request->input('stage')) {
            $query->where('stage', $stage);
        }
        if ($grade = $request->input('grade')) {
            $query->where('grade', $grade);
        }
        if ($knowledge_area_id = $request->input('knowledge_area_id')) {
            $query->where('knowledge_area_id', $knowledge_area_id);
        }

        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'oldest' => $query->oldest(),
            'level_asc' => $query->orderByRaw("FIELD(level, 'very_easy','easy','medium','hard','very_hard')"),
            'level_desc' => $query->orderByRaw("FIELD(level, 'very_hard','hard','medium','easy','very_easy')"),
            default => $query->latest(),
        };

        $questions = $query->paginate(15);

        $result = $questions->through(function ($q) use ($examQuestionIds) {
            return [
                'id' => $q->id,
                'type' => $q->type,
                'content' => $q->content,
                'level' => $q->level,
                'stage' => $q->stage,
                'grade' => $q->grade,
                'discipline' => $q->discipline?->name,
                'knowledge_area' => $q->knowledgeArea?->name,
                'owner_name' => $q->owner?->name,
                'owner_id' => $q->owner_id,
                'in_exam' => in_array($q->id, $examQuestionIds),
                'created_at' => $q->created_at->diffForHumans(),
            ];
        });

        return response()->json($result);
    }

    public function addQuestions(Request $request, string $id)
    {
        $exam = Exam::where('organization_id', auth()->user()->organization_id)
            ->where('author_id', auth()->id())
            ->findOrFail($id);

        $validated = $request->validate([
            'question_ids' => 'required|array|min:1',
            'question_ids.*' => 'integer|distinct',
            'points' => 'required|numeric|min:0',
        ]);

        $requestedIds = collect($validated['question_ids'])->map(fn ($questionId) => (int) $questionId)->values();
        $allowedIds = Question::where('organization_id', $exam->organization_id)
            ->whereIn('id', $requestedIds)
            ->where(function ($query) {
                $query->where('owner_id', auth()->id())
                    ->orWhere('visibility_scope', 'org_public')
                    ->orWhereHas('shares', fn ($share) => $share->where('shared_with_user_id', auth()->id()));
            })
            ->pluck('id')
            ->map(fn ($questionId) => (int) $questionId);

        if ($allowedIds->count() !== $requestedIds->count()) {
            throw ValidationException::withMessages([
                'question_ids' => 'Uma ou mais questões não estão disponíveis para esta avaliação.',
            ]);
        }

        DB::transaction(function () use ($exam, $requestedIds, $validated) {
            $currentMax = $exam->questions()->max('exam_questions.order') ?? 0;
            $existingIds = $exam->questions()->pluck('questions.id')->map(fn ($id) => (int) $id)->all();

            foreach ($requestedIds as $questionId) {
                if (! in_array($questionId, $existingIds, true)) {
                    $currentMax++;
                    $exam->questions()->attach($questionId, [
                        'points' => $validated['points'],
                        'order' => $currentMax,
                    ]);
                    $existingIds[] = $questionId;
                }
            }
        });

        $questions = $exam->questions()->with(['discipline', 'knowledgeArea'])->orderBy('exam_questions.order')->get();

        return response()->json([
            'success' => true,
            'questions' => $questions->map(fn ($q) => [
                'id' => $q->id,
                'type' => $q->type,
                'content' => $q->content,
                'level' => $q->level,
                'discipline' => $q->discipline?->name,
                'points' => $q->pivot->points,
                'order' => $q->pivot->order,
            ]),
        ]);
    }

    public function updateQuestionPoints(Request $request, string $id, string $question_id)
    {
        $exam = Exam::where('organization_id', auth()->user()->organization_id)
            ->where('author_id', auth()->id())
            ->findOrFail($id);

        $validated = $request->validate([
            'points' => 'required|numeric|min:0',
        ]);

        $exam->questions()->updateExistingPivot($question_id, ['points' => $validated['points']]);

        return response()->json(['success' => true]);
    }

    public function reorderQuestions(Request $request, string $id)
    {
        $exam = Exam::where('organization_id', auth()->user()->organization_id)
            ->where('author_id', auth()->id())
            ->findOrFail($id);

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer',
        ]);

        foreach ($validated['order'] as $index => $questionId) {
            $exam->questions()->updateExistingPivot($questionId, ['order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    public function gradeSubmission(string $id, string $submission_id)
    {
        $exam = Exam::where('organization_id', auth()->user()->organization_id)
            ->where('author_id', auth()->id())
            ->with('questions')
            ->findOrFail($id);

        $submission = ExamSubmission::with(['user', 'answers.question'])
            ->where('exam_id', $exam->id)
            ->findOrFail($submission_id);

        return view('exams.grade', compact('exam', 'submission'));
    }

    public function storeGrade(Request $request, string $id, string $submission_id)
    {
        $exam = Exam::where('organization_id', auth()->user()->organization_id)
            ->where('author_id', auth()->id())
            ->with('questions')
            ->findOrFail($id);

        $submission = ExamSubmission::with('answers')
            ->where('exam_id', $exam->id)
            ->findOrFail($submission_id);

        $validator = Validator::make($request->all(), [
            'points' => ['required', 'array'],
            'points.*' => ['required', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'array'],
            'feedback.*' => ['nullable', 'string', 'max:5000'],
            'justification' => ['nullable', 'array'],
            'justification.*' => ['nullable', 'string', 'max:5000'],
            'rubric_scores' => ['nullable', 'array'],
            'rubric_scores.*' => ['nullable', 'array'],
            'rubric_scores.*.*' => ['nullable', 'numeric', 'min:0'],
            'general_feedback' => ['nullable', 'string', 'max:10000'],
        ]);

        $examQuestions = $exam->questions->keyBy('id');
        $examQuestionIds = $examQuestions->keys()->map(fn ($id) => (int) $id);
        $answers = $submission->answers
            ->whereIn('question_id', $examQuestionIds)
            ->keyBy('id');

        $validator->after(function ($validator) use ($request, $submission, $examQuestions, $answers, $examQuestionIds) {
            $submittedPoints = $request->input('points', []);

            if (! is_array($submittedPoints)) {
                return;
            }

            if (! in_array($submission->status, ['submitted', 'graded'], true)) {
                $validator->errors()->add(
                    'points',
                    'A submissão precisa ser finalizada antes da correção.'
                );
            }

            $answeredQuestionIds = $answers->pluck('question_id')
                ->map(fn ($id) => (int) $id);
            $hasExactlyOneAnswerPerQuestion = $answers->count() === $examQuestionIds->count()
                && $answeredQuestionIds->unique()->count() === $examQuestionIds->count()
                && $answeredQuestionIds->sort()->values()->all() === $examQuestionIds->sort()->values()->all();

            if (! $hasExactlyOneAnswerPerQuestion) {
                $validator->errors()->add(
                    'points',
                    'A submissão não possui respostas para todas as questões desta avaliação.'
                );
            }

            foreach ($answers as $answerId => $answer) {
                $field = "points.{$answerId}";

                if (! array_key_exists($answerId, $submittedPoints)) {
                    $validator->errors()->add($field, 'Informe a nota desta resposta.');

                    continue;
                }

                $maximum = (float) $examQuestions->get($answer->question_id)->pivot->points;
                if (is_numeric($submittedPoints[$answerId])
                    && (float) $submittedPoints[$answerId] > $maximum) {
                    $validator->errors()->add(
                        $field,
                        "A nota não pode ultrapassar {$maximum} ponto(s)."
                    );
                }

                $rubricScores = $request->input("rubric_scores.{$answerId}", []);
                if (! is_array($rubricScores) || $rubricScores === []) {
                    continue;
                }

                $question = $examQuestions->get($answer->question_id);
                $criteria = data_get($question->content, 'rubric.criteria', []);

                foreach ($rubricScores as $criterionIndex => $score) {
                    $rubricField = "rubric_scores.{$answerId}.{$criterionIndex}";

                    if (! isset($criteria[$criterionIndex])) {
                        $validator->errors()->add($rubricField, 'Critério de rubrica inválido.');

                        continue;
                    }

                    $criterionMaximum = (float) ($criteria[$criterionIndex]['points'] ?? 0);
                    if (is_numeric($score) && (float) $score > $criterionMaximum) {
                        $validator->errors()->add(
                            $rubricField,
                            "A pontuação do critério não pode ultrapassar {$criterionMaximum}."
                        );
                    }
                }
            }

            foreach (array_keys($submittedPoints) as $answerId) {
                if (! $answers->has($answerId)) {
                    $validator->errors()->add(
                        "points.{$answerId}",
                        'A resposta informada não pertence a esta submissão.'
                    );
                }
            }
        });

        $validated = $validator->validate();

        DB::transaction(function () use ($submission, $exam, $examQuestions, $answers, $validated) {
            $lockedSubmission = ExamSubmission::whereKey($submission->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAnswers = ExamAnswer::where('exam_submission_id', $lockedSubmission->id)
                ->whereIn('id', $answers->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $previousSubmission = [
                'score' => $lockedSubmission->score,
                'status' => $lockedSubmission->status,
                'feedback' => $lockedSubmission->feedback,
            ];
            $answerChanges = [];
            $totalScore = 0.0;

            foreach ($answers as $answerId => $loadedAnswer) {
                $answer = $lockedAnswers->get($answerId);
                $question = $examQuestions->get($answer->question_id);
                $maximum = (float) $question->pivot->points;
                $points = (float) $validated['points'][$answerId];
                $feedback = data_get($validated, "feedback.{$answerId}");
                $justification = data_get($validated, "justification.{$answerId}");
                $rubricScores = data_get($validated, "rubric_scores.{$answerId}");
                if (is_array($rubricScores)) {
                    $rubricScores = array_map(
                        fn ($score) => is_null($score) ? null : (float) $score,
                        $rubricScores
                    );
                }

                $previousAnswer = [
                    'points_awarded' => $answer->points_awarded,
                    'is_correct' => $answer->is_correct,
                    'feedback' => $answer->feedback,
                    'grading_justification' => $answer->grading_justification,
                    'rubric_scores' => $answer->rubric_scores,
                ];

                $newAnswer = [
                    'points_awarded' => $points,
                    'is_correct' => abs($points - $maximum) < 0.00001,
                    'feedback' => $feedback,
                    'grading_justification' => $justification,
                    'rubric_scores' => $rubricScores,
                ];

                $answer->update($newAnswer);
                $totalScore += $points;

                $answerChanges[] = [
                    'answer_id' => $answer->id,
                    'question_id' => $answer->question_id,
                    'maximum_points' => $maximum,
                    'old' => $previousAnswer,
                    'new' => $newAnswer,
                ];
            }

            $newSubmission = [
                'score' => $totalScore,
                'status' => 'graded',
                'feedback' => $validated['general_feedback'] ?? null,
            ];
            $lockedSubmission->update($newSubmission);

            AuditLog::log('submission_graded', ExamSubmission::class, $lockedSubmission->id, [
                'exam_id' => $exam->id,
                'student_id' => $lockedSubmission->user_id,
                'old' => $previousSubmission,
                'new' => $newSubmission,
                'answers' => $answerChanges,
            ]);
        });

        return redirect()->route('exams.show', $exam->id)->with('status', 'Correção salva com sucesso!');
    }

    public function exportPdf(string $id)
    {
        if (! auth()->user()->hasFeature('export_pdf')) {
            return back()->withErrors(['O plano atual da sua instituição não permite exportação de PDFs.']);
        }

        $exam = Exam::where('organization_id', auth()->user()->organization_id)
            ->where('author_id', auth()->id())
            ->with(['questions.discipline'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('exams.pdf', compact('exam'));

        return $pdf->download('avaliacao_'.str_replace(' ', '_', strtolower($exam->title)).'.pdf');
    }

    public function printAdvanced(Request $request, string $id)
    {
        if (! auth()->user()->hasFeature('export_pdf')) {
            return back()->withErrors(['O plano atual da sua instituição não permite exportação de PDFs.']);
        }

        $exam = Exam::where('organization_id', auth()->user()->organization_id)
            ->where('author_id', auth()->id())
            ->with(['questions.discipline', 'organization'])
            ->findOrFail($id);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:200',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'shuffle_questions' => 'nullable|boolean',
            'shuffle_options_mc' => 'nullable|boolean',
            'shuffle_options_tf' => 'nullable|boolean',

            'group_disciplines' => 'nullable|boolean',
            'shuffle_disciplines' => 'nullable|boolean',
            'show_discipline_name' => 'nullable|boolean',
            'hide_question_term' => 'nullable|boolean',
            'show_question_value' => 'nullable|boolean',
            'show_option_brackets' => 'nullable|boolean',
            'question_separator' => 'required|string|max:10',
            'custom_separator' => 'nullable|string|max:3',
        ]);

        $finalSeparator = $validated['question_separator'];
        if ($finalSeparator === 'custom') {
            $finalSeparator = $validated['custom_separator'] ?? '.';
        }

        $options = [
            'group_disciplines' => $request->boolean('group_disciplines'),
            'shuffle_disciplines' => $request->boolean('shuffle_disciplines'),
            'show_discipline_name' => $request->boolean('show_discipline_name'),

            'shuffle_questions' => $request->boolean('shuffle_questions'),
            'shuffle_options_mc' => $request->boolean('shuffle_options_mc'),
            'shuffle_options_tf' => $request->boolean('shuffle_options_tf'),

            'hide_question_term' => $request->boolean('hide_question_term'),
            'show_question_value' => $request->boolean('show_question_value'),
            'show_option_brackets' => $request->boolean('show_option_brackets'),
            'question_separator' => $finalSeparator,
        ];

        // Calibração OMR fixa (não exposta ao usuário por enquanto)
        $calibration = [
            'offset_x' => 0,
            'offset_y' => 0,
            'scale' => 90,
        ];

        // Cartão-resposta OMR do lote: usa a MESMA geração legível pelo motor (geometria
        // absoluta + QR assinado com `g`), não mais o cartão de tabela antigo.
        $numQ = $exam->questions->count();
        $template = $exam->cardTemplate;
        if (! $template || ! $template->is_active) {
            // Escolhe o menor template do sistema que comporte a prova.
            $template = OmrTemplate::where('is_system', true)->where('is_active', true)
                ->where('max_questions', '>=', $numQ)->orderBy('max_questions')->first()
                ?? OmrTemplate::where('is_default', true)->where('is_active', true)->first();
        }

        if (! $template) {
            return back()->withInput()->withErrors([
                'print' => 'Nenhum template OMR ativo comporta esta avaliação. Ative ou crie um template antes de gerar o lote.',
            ]);
        }

        try {
            [$copies, $cardPagesByCopy] = DB::transaction(function () use (
                $exam,
                $validated,
                $options,
                $template
            ): array {
                $printService = app(ExamPrintService::class);
                $copies = $printService->generateCopies(
                    $exam,
                    $validated['quantity'],
                    $options,
                    $validated['school_class_id'] ?? null
                );

                // Frame ajustado para a área de conteúdo da folha. A geometria
                // final ainda é validada pelo gerador antes de qualquer commit.
                $loteLayout = array_merge($template->currentLayout(), [
                    'frame_left_mm' => 8.0,
                    'frame_top_mm' => 54.0,
                    'frame_width_mm' => 174.0,
                ]);
                $generator = app(AnswerSheetGeneratorService::class);
                $cardPagesByCopy = [];

                foreach ($copies as $copy) {
                    $cardPagesByCopy[$copy->id] = $generator->buildCardPages($exam, $copy, $template, 'hybrid', $loteLayout);
                }

                return [$copies, $cardPagesByCopy];
            });
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors([
                'print' => 'Não foi possível gerar o lote: confira o template OMR e a geometria da folha.',
            ]);
        }

        $pdf = Pdf::loadView('exams.pdf_advanced', compact('exam', 'copies', 'calibration', 'options', 'cardPagesByCopy'));

        return $pdf->stream('lote_'.str_replace(' ', '_', strtolower($exam->title)).'.pdf');
    }

    private function getPrintSettings(User $user)
    {
        $systemDefaults = [
            'group_disciplines' => true,
            'shuffle_disciplines' => false,
            'show_discipline_name' => true,
            'hide_question_term' => false,
            'show_question_value' => true,
            'show_option_brackets' => false,
            'question_separator' => '.',
        ];

        $orgSettings = $user->organization ? ($user->organization->settings['print'] ?? []) : [];
        $userSettings = $user->settings['print'] ?? [];

        return array_merge($systemDefaults, $orgSettings, $userSettings);
    }

    public function destroy(string $id)
    {
        $exam = Exam::where('organization_id', auth()->user()->organization_id)
            ->where('author_id', auth()->id())
            ->findOrFail($id);

        $exam->delete();

        return redirect()->route('exams.index')->with('status', 'Avaliação excluída com sucesso.');
    }

    public function duplicate(string $id)
    {
        $original = Exam::where('organization_id', auth()->user()->organization_id)
            ->with('questions')
            ->findOrFail($id);

        $copy = Exam::create([
            'organization_id' => auth()->user()->organization_id,
            'author_id' => auth()->id(),
            'title' => $original->title.' (Cópia)',
            'status' => 'draft',
            'access_code' => strtoupper(substr(md5(uniqid()), 0, 4)),
            'settings' => $original->settings,
        ]);

        // Clone question associations with points and order
        foreach ($original->questions as $question) {
            $copy->questions()->attach($question->id, [
                'points' => $question->pivot->points,
                'order' => $question->pivot->order,
            ]);
        }

        return redirect()->route('exams.edit', $copy->id)
            ->with('status', 'Avaliação duplicada! Edite os detalhes conforme necessário.');
    }

    /**
     * Generate Answer Sheet PDF for an Exam using the new OMR infrastructure.
     */
    public function exportAnswerSheet(
        Request $request,
        Exam $exam,
        ExamPrintService $printService,
        AnswerSheetGeneratorService $generatorService,
        ConfigPrecedenceResolver $configResolver
    ) {
        $user = $request->user();

        // Route-model binding alone does not enforce authorship for this endpoint.
        $exam = Exam::whereKey($exam->getKey())
            ->where('organization_id', $user->organization_id)
            ->where('author_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:100',
            'shuffle_questions' => 'nullable|boolean',
            'shuffle_options_mc' => 'nullable|boolean',
            'shuffle_options_tf' => 'nullable|boolean',
            'group_disciplines' => 'nullable|boolean',
            'shuffle_disciplines' => 'nullable|boolean',
            'school_class_id' => 'nullable|integer',
            'card_template_id' => 'nullable|integer',
        ]);

        $quantity = $validated['quantity'];
        $schoolClassId = null;
        if (! empty($validated['school_class_id'])) {
            $schoolClassId = SchoolClass::withoutGlobalScopes()
                ->where('organization_id', $exam->organization_id)
                ->whereKey($validated['school_class_id'])
                ->value('id');

            if (! $schoolClassId) {
                throw ValidationException::withMessages([
                    'school_class_id' => 'A turma selecionada não pertence à instituição desta avaliação.',
                ]);
            }
        }

        $options = $request->only([
            'shuffle_questions',
            'shuffle_options_mc',
            'shuffle_options_tf',
            'group_disciplines',
            'shuffle_disciplines',
        ]);

        $organizationId = $exam->organization_id;
        $userId = $user->id;

        // Resolve o TEMPLATE de cartão: o enviado no form, ou o já vinculado à prova,
        // ou o template PADRÃO do sistema. Valida visibilidade ao usuário.
        $requestedTemplateId = $validated['card_template_id'] ?? null;
        $templateId = $requestedTemplateId ?: $exam->card_template_id;
        $template = null;
        if ($templateId) {
            $template = OmrTemplate::visible($user)->where('id', $templateId)->where('is_active', true)->first();
        }
        if ($requestedTemplateId && ! $template) {
            throw ValidationException::withMessages([
                'card_template_id' => 'O template selecionado não está disponível para esta instituição.',
            ]);
        }
        if (! $template) {
            $template = OmrTemplate::visible($user)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();
        }
        if (! $template) {
            return back()->withErrors('Nenhum template de cartão-resposta disponível. Execute o seeder do template padrão.');
        }

        // Modo de leitura (gabarito embutido/híbrido) continua via config; padrão híbrido.
        $scanMode = $configResolver->resolveWithTrace($organizationId, $userId, 'scan_mode')['effective_value'] ?? 'hybrid';

        // Registra o vínculo prova↔template (id + versão usada na geração).
        if ($exam->card_template_id !== $template->id || $exam->card_template_version !== $template->current_version) {
            $exam->forceFill([
                'card_template_id' => $template->id,
                'card_template_version' => $template->current_version,
            ])->save();
        }

        // Gera as cópias (embaralhamento) e inclui todas elas no mesmo PDF.
        $copies = $printService->generateCopies($exam, $quantity, $options, $schoolClassId);
        if ($copies->isEmpty()) {
            return back()->withErrors('Não foi possível gerar as cópias da prova.');
        }

        try {
            $result = $generatorService->generate($exam, $copies, $template, $scanMode);
        } catch (\RuntimeException $e) {
            return back()->withErrors($e->getMessage());
        }

        return $result['pdf']->stream("Cartoes_Resposta_{$exam->id}_{$copies->count()}_copias.pdf");
    }

    /**
     * Regras compartilhadas pelo primeiro passo e pelo editor da avaliação.
     *
     * @return array<string, array<int, string>|string>
     */
    private function settingsValidationRules(): array
    {
        return [
            'settings_form' => ['nullable', 'boolean'],
            'application_mode' => ['nullable', 'in:online,paper,hybrid'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'time_limit' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'attempts' => ['nullable', 'integer', 'min:1', 'max:20'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after:available_from'],
            'results_available_from' => ['nullable', 'date'],
            'shuffle_questions' => ['nullable', 'boolean'],
            'shuffle_options' => ['nullable', 'boolean'],
            'show_score' => ['nullable', 'boolean'],
            'show_answers' => ['nullable', 'boolean'],
            'show_feedback' => ['nullable', 'boolean'],
            // Compatibilidade com clientes e provas criadas antes da liberação granular.
            'show_results' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Mescla atualizações parciais sem apagar configurações de formulários rápidos.
     *
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function settingsFromRequest(Request $request, array $current = []): array
    {
        $settings = array_merge([
            'application_mode' => 'hybrid',
            'instructions' => null,
            'time_limit' => null,
            'attempts' => 1,
            'available_from' => null,
            'available_until' => null,
            'results_available_from' => null,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'show_score' => true,
            'show_answers' => false,
            'show_feedback' => true,
        ], $current);

        $fullForm = $request->boolean('settings_form');
        $scalarFields = [
            'application_mode',
            'instructions',
            'time_limit',
            'attempts',
            'available_from',
            'available_until',
            'results_available_from',
        ];

        foreach ($scalarFields as $field) {
            if (! $fullForm && ! $request->exists($field)) {
                continue;
            }

            $value = $request->input($field);
            $settings[$field] = match ($field) {
                'instructions' => filled($value) ? trim((string) $value) : null,
                'time_limit' => filled($value) ? (int) $value : null,
                'attempts' => filled($value) ? (int) $value : 1,
                'available_from', 'available_until', 'results_available_from' => filled($value)
                    ? Carbon::parse($value)->toIso8601String()
                    : null,
                'application_mode' => filled($value) ? (string) $value : 'hybrid',
                default => $value,
            };
        }

        $granularReleaseProvided = $request->hasAny([
            'show_score',
            'show_answers',
            'show_feedback',
        ]);
        if ($request->exists('show_results') && ! $granularReleaseProvided) {
            $legacyRelease = $request->boolean('show_results');
            $settings['show_score'] = $legacyRelease;
            $settings['show_answers'] = $legacyRelease;
            $settings['show_feedback'] = $legacyRelease;
        } else {
            foreach ([
                'shuffle_questions',
                'shuffle_options',
                'show_score',
                'show_answers',
                'show_feedback',
            ] as $field) {
                if ($fullForm || $request->exists($field)) {
                    $settings[$field] = $request->boolean($field);
                }
            }
        }

        unset($settings['show_results']);

        return $settings;
    }

    /**
     * Impede que um rascunho incompleto seja exposto aos estudantes.
     *
     * @param  array<string, mixed>  $settings
     */
    private function ensurePublishable(Exam $exam, array $settings): void
    {
        if (! $exam->questions()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Adicione ao menos uma questão antes de publicar a avaliação.',
            ]);
        }

        if ((float) $exam->questions()->sum('exam_questions.points') <= 0) {
            throw ValidationException::withMessages([
                'status' => 'A pontuação total deve ser maior que zero antes da publicação.',
            ]);
        }

        if (filled($settings['available_until'] ?? null)
            && ! now()->isBefore(Carbon::parse($settings['available_until']))) {
            throw ValidationException::withMessages([
                'available_until' => 'Defina um prazo futuro antes de publicar a avaliação.',
            ]);
        }
    }

    private function generateAccessCode(int $organizationId): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (
            Exam::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('access_code', $code)
                ->exists()
        );

        return $code;
    }
}
