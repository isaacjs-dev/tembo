<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\QuotaExceededException;
use App\Http\Controllers\Controller;
use App\Jobs\ConsolidateAnswersJob;
use App\Models\Exam;
use App\Models\ExamCopy;
use App\Models\OmrScan;
use App\Models\OmrScanPage;
use App\Models\User;
use App\Services\MonthlyUsageService;
use App\Services\OfflineOmrQrService;
use App\Services\OmrGradingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OmrApiController extends Controller
{
    public function __construct(
        private OmrGradingService $gradingService,
        private OfflineOmrQrService $offlineQrService,
        private MonthlyUsageService $monthlyUsage,
    ) {}

    public function index(Request $request)
    {
        $orgId = $request->user()->organization_id;

        $scans = OmrScan::where('organization_id', $orgId)
            ->with(['exam:id,title', 'student:id,name', 'copy:id,copy_number'])
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($scans);
    }

    public function show(Request $request, OmrScan $scan)
    {
        $orgId = $request->user()->organization_id;

        if ($scan->organization_id !== $orgId) {
            return response()->json(['error' => 'Acesso negado.'], 403);
        }

        $scan->load(['exam:id,title', 'student:id,name', 'uploader:id,name', 'copy:id,copy_number,validation_hash']);

        return response()->json(['scan' => $scan]);
    }

    public function store(Request $request)
    {
        $orgId = (int) $request->user()->organization_id;
        abort_unless($orgId, 403);

        $validated = $request->validate([
            'session_id' => 'required|uuid',
            'exam_id' => [
                'required',
                'integer',
                Rule::exists('exams', 'id')->where(
                    fn ($query) => $query->where('organization_id', $orgId)->whereNull('deleted_at')
                ),
            ],
            'copy_id' => [
                'nullable',
                'integer',
                Rule::exists('exam_copies', 'id')->where(
                    fn ($query) => $query->where('exam_id', $request->input('exam_id'))
                ),
            ],
            'student_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query
                        ->where('organization_id', $orgId)
                        ->where('type', 'student')
                        ->where('status', 'active')
                        ->whereNull('deleted_at')
                ),
            ],
            'page_index' => 'required|integer|min:1|max:20|lte:total_pages',
            'total_pages' => 'required|integer|min:1|max:20',
            'image' => 'required|image|max:10240',
            'detected_answers' => 'required|json|max:100000',
            'confidences' => 'nullable|json|max:100000',
            'overall_confidence' => 'nullable|numeric|min:0|max:1',
            // Contingência offline: QR assinado, sem gabarito aberto. As chaves de
            // detected_answers são os números impressos (qs..qe), não IDs internos.
            'qr_payload' => 'nullable|json|max:100000',
            'question_start' => 'nullable|integer|min:1|max:1000',
        ]);

        $detectedAnswers = json_decode($validated['detected_answers'], true);
        $confidences = isset($validated['confidences'])
            ? json_decode($validated['confidences'], true)
            : null;

        if (! is_array($detectedAnswers) || ($confidences !== null && ! is_array($confidences))) {
            throw ValidationException::withMessages([
                'detected_answers' => 'As respostas e confianças devem ser objetos JSON.',
            ]);
        }

        $quotaSubject = User::query()->findOrFail(
            Exam::withoutGlobalScopes()->whereKey($validated['exam_id'])->value('author_id')
        );
        $quota = $this->monthlyUsage->snapshot($quotaSubject, MonthlyUsageService::OMR_SCANS);
        if ($quota['remaining'] !== null && $quota['remaining'] < 1) {
            throw new QuotaExceededException(MonthlyUsageService::OMR_SCANS, 1, $quota['remaining']);
        }

        $qrPayload = isset($validated['qr_payload'])
            ? json_decode($validated['qr_payload'], true)
            : null;
        $offlineMetadata = null;
        if ($qrPayload !== null) {
            if (! is_array($qrPayload) || empty($validated['copy_id']) || empty($validated['question_start'])) {
                throw ValidationException::withMessages([
                    'qr_payload' => 'A captura offline exige qr_payload, copy_id e question_start.',
                ]);
            }

            $exam = Exam::withoutGlobalScopes()
                ->whereKey($validated['exam_id'])
                ->where('organization_id', $orgId)
                ->with('questions')
                ->firstOrFail();
            $copy = ExamCopy::query()
                ->whereKey($validated['copy_id'])
                ->where('exam_id', $exam->id)
                ->firstOrFail();

            $offlineMetadata = $this->offlineQrService->validatePage(
                $qrPayload,
                $orgId,
                $exam,
                $copy,
                (int) $validated['page_index'],
                (int) $validated['total_pages'],
                (int) $validated['question_start'],
            );

            // Validar os limites agora, antes de aceitar imagem/arquivo da fila. O
            // mapeamento definitivo ocorre no job de sincronização, sob transação.
            $this->offlineQrService->mapPrintedAnswers(
                $copy,
                $detectedAnswers,
                $offlineMetadata['question_start'],
                $offlineMetadata['question_end'],
            );
            if ($confidences !== null) {
                $this->offlineQrService->mapPrintedConfidences(
                    $copy,
                    $confidences,
                    $offlineMetadata['question_start'],
                    $offlineMetadata['question_end'],
                );
            }
        }

        // A session is owned by one institution, uploader and exam context.
        $sessionPage = OmrScanPage::where('session_id', $validated['session_id'])->first();
        if ($sessionPage) {
            $sessionOrgId = $sessionPage->organization_id
                ?: Exam::withoutGlobalScopes()->whereKey($sessionPage->exam_id)->value('organization_id');

            if ((int) $sessionOrgId !== $orgId || (int) $sessionPage->uploaded_by !== (int) $request->user()->id) {
                return response()->json(['error' => 'Sessão de leitura indisponível.'], 409);
            }

            $sameContext = (int) $sessionPage->exam_id === (int) $validated['exam_id']
                && (int) $sessionPage->total_pages === (int) $validated['total_pages']
                && (int) ($sessionPage->copy_id ?? 0) === (int) ($validated['copy_id'] ?? 0)
                && (int) ($sessionPage->student_id ?? 0) === (int) ($validated['student_id'] ?? 0);

            if (! $sameContext) {
                throw ValidationException::withMessages([
                    'session_id' => 'A sessão já foi iniciada com outra prova, cópia, estudante ou quantidade de páginas.',
                ]);
            }
        }

        // Check if this page was already uploaded for this session
        $existingPage = OmrScanPage::where('organization_id', $orgId)
            ->where('uploaded_by', $request->user()->id)
            ->where('session_id', $validated['session_id'])
            ->where('page_index', $validated['page_index'])
            ->first();

        if ($existingPage) {
            return response()->json(['page' => $existingPage, 'duplicate' => true], 200);
        }

        // Store image
        $path = $request->file('image')->store('omr-scans/pages/'.$orgId, 'public');

        $page = OmrScanPage::create([
            'organization_id' => $orgId,
            'uploaded_by' => $request->user()->id,
            'session_id' => $validated['session_id'],
            'exam_id' => $validated['exam_id'],
            'copy_id' => $validated['copy_id'] ?? null,
            'student_id' => $validated['student_id'] ?? null,
            'page_index' => $validated['page_index'],
            'total_pages' => $validated['total_pages'],
            'image_path' => $path,
            'qr_payload' => $qrPayload,
            'raw_answers' => $detectedAnswers,
            'raw_confidences' => $confidences,
            'overall_confidence' => $validated['overall_confidence'] ?? 0,
            'status' => 'pending',
        ]);

        // Check if all pages for this session have been uploaded
        $uploadedPagesCount = OmrScanPage::where('organization_id', $orgId)
            ->where('uploaded_by', $request->user()->id)
            ->where('session_id', $validated['session_id'])
            ->count();

        if ($uploadedPagesCount >= $validated['total_pages']) {
            // Queue consolidation job
            ConsolidateAnswersJob::dispatch($validated['session_id'], $orgId);
            Log::info("Dispatched ConsolidateAnswersJob for session {$validated['session_id']}");
        }

        return response()->json([
            'page' => $page,
            'progress' => [
                'uploaded' => $uploadedPagesCount,
                'total' => $validated['total_pages'],
                'is_complete' => $uploadedPagesCount >= $validated['total_pages'],
            ],
        ], 201);
    }

    public function confirm(Request $request, OmrScan $scan)
    {
        $orgId = $request->user()->organization_id;

        if ($scan->organization_id !== $orgId) {
            return response()->json(['error' => 'Acesso negado.'], 403);
        }

        $validated = $request->validate([
            'student_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query
                        ->where('organization_id', $orgId)
                        ->where('type', 'student')
                        ->where('status', 'active')
                        ->whereNull('deleted_at')
                ),
            ],
            'confirmed_answers' => 'required|array',
        ]);

        $exam = Exam::withoutGlobalScopes()
            ->whereKey($scan->exam_id)
            ->where('organization_id', $orgId)
            ->with('questions')
            ->firstOrFail();

        $copy = $scan->copy_id
            ? ExamCopy::whereKey($scan->copy_id)->where('exam_id', $exam->id)->firstOrFail()
            : null;

        $gradingResult = DB::transaction(function () use ($scan, $exam, $copy, $validated) {
            $scan->update([
                'student_id' => $validated['student_id'],
                'confirmed_answers' => $validated['confirmed_answers'],
                'status' => 'confirmed',
                'confidence_score' => 1.0,
            ]);
            $scan->setRelation('exam', $exam);

            return $this->gradingService->grade($scan, $validated['confirmed_answers'], $copy);
        });
        $submission = $gradingResult['submission'];

        return response()->json([
            'scan' => $scan,
            'submission' => [
                'id' => $submission->id,
                'score' => $submission->score,
                'status' => $submission->status,
            ],
        ]);
    }

    public function reject(Request $request, OmrScan $scan)
    {
        $orgId = $request->user()->organization_id;

        if ($scan->organization_id !== $orgId) {
            return response()->json(['error' => 'Acesso negado.'], 403);
        }

        $scan->update([
            'status' => 'rejected',
            'notes' => $request->input('notes', 'Rejeitado via app mobile.'),
        ]);

        return response()->json(['scan' => $scan]);
    }
}
