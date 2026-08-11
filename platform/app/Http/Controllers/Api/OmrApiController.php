<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\QuotaExceededException;
use App\Http\Controllers\Controller;
use App\Jobs\ConsolidateAnswersJob;
use App\Models\Exam;
use App\Models\ExamCopy;
use App\Models\OmrScan;
use App\Models\OmrScanPage;
use App\Models\Organization;
use App\Models\User;
use App\Rules\ActiveOrganizationMember;
use App\Services\MonthlyUsageService;
use App\Services\OfflineOmrQrService;
use App\Services\OmrGradingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'idempotency_key' => 'nullable|string|max:190',
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
                new ActiveOrganizationMember($orgId, 'student'),
            ],
            'page_index' => 'required|integer|min:1|max:20|lte:total_pages',
            'total_pages' => 'required|integer|min:1|max:20',
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'detected_answers' => 'required|json|max:100000',
            'confidences' => 'nullable|json|max:100000',
            'overall_confidence' => 'nullable|numeric|min:0|max:1',
            'processing_evidence' => 'nullable|json|max:100000',
            // Contingência offline: QR assinado, sem gabarito aberto. As chaves de
            // detected_answers são os números impressos (qs..qe), não IDs internos.
            'qr_payload' => 'nullable|json|max:100000',
            'question_start' => 'nullable|integer|min:1|max:1000',
        ]);

        $detectedAnswers = json_decode($validated['detected_answers'], true);
        $confidences = isset($validated['confidences'])
            ? json_decode($validated['confidences'], true)
            : null;
        $processingEvidence = isset($validated['processing_evidence'])
            ? json_decode($validated['processing_evidence'], true)
            : null;

        if (! is_array($detectedAnswers) || ($confidences !== null && ! is_array($confidences))) {
            throw ValidationException::withMessages([
                'detected_answers' => 'As respostas e confianças devem ser objetos JSON.',
            ]);
        }
        if ($processingEvidence !== null) {
            if (! is_array($processingEvidence)) {
                throw ValidationException::withMessages([
                    'processing_evidence' => 'A evidência de processamento deve ser um objeto JSON.',
                ]);
            }
            $processingEvidence = Validator::make($processingEvidence, [
                'pipelineVersion' => ['required', Rule::in(['mobile-v2'])],
                'processingPath' => ['required', Rule::in(['homography', 'hybrid', 'manual', 'unavailable'])],
                'action' => ['required', Rule::in(['accept', 'review', 'rescan'])],
                'reasons' => 'present|array|max:20',
                'reasons.*' => 'string|max:80',
                'imageQuality' => 'nullable|array',
                'imageQuality.brightness' => 'nullable|numeric|min:0|max:255',
                'imageQuality.contrast' => 'nullable|numeric|min:0|max:255',
                'imageQuality.laplacianVariance' => 'nullable|numeric|min:0|max:10000000',
                'imageQuality.acceptable' => 'nullable|boolean',
                'imageQuality.reasons' => 'nullable|array|max:20',
                'imageQuality.reasons.*' => 'string|max:80',
                'geometry' => 'required|array',
                'geometry.fiducialCount' => 'required|integer|min:0|max:4',
                'geometry.fiducialConfidence' => 'required|numeric|min:0|max:1',
                'geometry.reprojectionError' => 'nullable|array',
                'geometry.reprojectionError.rms' => 'required_with:geometry.reprojectionError|numeric|min:0|max:10000',
                'geometry.reprojectionError.max' => 'required_with:geometry.reprojectionError|numeric|min:0|max:10000',
                'geometry.orientationDegrees' => 'nullable|numeric|min:-180|max:180',
                'geometry.scaleRatio' => 'nullable|numeric|min:0|max:2',
                'questions' => 'required|array|min:1|max:1000',
                'questions.*.action' => ['required', Rule::in(['accept', 'review'])],
                'questions.*.reasons' => 'present|array|max:20',
                'questions.*.reasons.*' => 'string|max:80',
                'questions.*.fillRatios' => 'required|array|max:9',
                'questions.*.fillRatios.*' => 'numeric|min:0|max:1',
                'questions.*.confidence' => 'required|numeric|min:0|max:1',
                'questions.*.roi' => 'required|array',
                'questions.*.roi.x' => 'required|integer|min:0|max:100000',
                'questions.*.roi.y' => 'required|integer|min:0|max:100000',
                'questions.*.roi.w' => 'required|integer|min:0|max:100000',
                'questions.*.roi.h' => 'required|integer|min:0|max:100000',
            ])->validate();
            if (($processingEvidence['action'] ?? null) === 'rescan') {
                throw ValidationException::withMessages([
                    'processing_evidence' => 'Uma captura marcada para repetição não pode ser sincronizada.',
                ]);
            }
            $this->assertProcessingEvidenceIsConsistent($processingEvidence);
        }

        $exam = Exam::withoutGlobalScopes()
            ->whereKey($validated['exam_id'])
            ->where('organization_id', $orgId)
            ->with('questions')
            ->firstOrFail();
        $copy = ! empty($validated['copy_id'])
            ? ExamCopy::query()->whereKey($validated['copy_id'])->where('exam_id', $exam->id)->firstOrFail()
            : null;
        if ($copy?->student_id) {
            $copyStudentIsActive = User::query()
                ->memberOfOrganization($orgId, 'student')
                ->whereKey($copy->student_id)
                ->exists();
            if (! $copyStudentIsActive) {
                throw ValidationException::withMessages([
                    'copy_id' => 'O aluno desta cópia não possui mais vínculo ativo com a organização.',
                ]);
            }
            if (! empty($validated['student_id'])
                && (int) $copy->student_id !== (int) $validated['student_id']) {
                throw ValidationException::withMessages([
                    'student_id' => 'Esta cópia individualizada pertence a outro aluno.',
                ]);
            }

            $validated['student_id'] = (int) $copy->student_id;
        }

        $quotaSubject = User::query()->findOrFail($exam->author_id);
        $quotaOrganization = Organization::query()->findOrFail($orgId);
        $quota = $this->monthlyUsage->snapshot(
            $quotaSubject,
            MonthlyUsageService::OMR_SCANS,
            $quotaOrganization,
        );
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

            if (! $copy) {
                throw ValidationException::withMessages([
                    'copy_id' => 'A captura offline exige uma cópia válida desta avaliação.',
                ]);
            }

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

        $uploaderId = (int) $request->user()->id;
        $idempotencyKey = trim((string) ($validated['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            // Backward-compatible deterministic key for old v1/v2 clients.
            $idempotencyKey = 'session:'.$validated['session_id'].':page:'.$validated['page_index'];
        }
        $requestFingerprint = hash('sha256', json_encode($this->canonicalizeFingerprintValue([
            'exam_id' => (int) $validated['exam_id'],
            'copy_id' => (int) ($validated['copy_id'] ?? 0),
            'student_id' => (int) ($validated['student_id'] ?? 0),
            'session_id' => $validated['session_id'],
            'page_index' => (int) $validated['page_index'],
            'total_pages' => (int) $validated['total_pages'],
            'answers' => $detectedAnswers,
            'confidences' => $confidences,
            'processing_evidence' => $processingEvidence,
            'qr_payload' => $qrPayload,
            'image_sha256' => hash_file('sha256', $request->file('image')->getRealPath()),
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $existingPage = OmrScanPage::query()
            ->where('organization_id', $orgId)
            ->where('uploaded_by', $uploaderId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existingPage) {
            return $this->duplicatePageResponse($request, $existingPage, $requestFingerprint);
        }

        // New captures are private; historical public files are served through
        // the authenticated endpoint for compatibility.
        $path = $request->file('image')->store('omr-scans/pages/'.$orgId, 'local');

        try {
            $page = DB::transaction(fn () => OmrScanPage::create([
                'organization_id' => $orgId,
                'uploaded_by' => $uploaderId,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $requestFingerprint,
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
                'processing_evidence' => $processingEvidence,
                'status' => 'pending',
            ]));
        } catch (QueryException $exception) {
            Storage::disk('local')->delete($path);
            $existingPage = OmrScanPage::query()
                ->where('organization_id', $orgId)
                ->where('uploaded_by', $uploaderId)
                ->where(function ($query) use ($idempotencyKey, $validated) {
                    $query->where('idempotency_key', $idempotencyKey)
                        ->orWhere(function ($pageQuery) use ($validated) {
                            $pageQuery->where('session_id', $validated['session_id'])
                                ->where('page_index', $validated['page_index']);
                        });
                })
                ->first();

            if (! $existingPage) {
                throw $exception;
            }

            return $this->duplicatePageResponse($request, $existingPage, $requestFingerprint);
        }

        // Check if all pages for this session have been uploaded
        $uploadedPagesCount = OmrScanPage::where('organization_id', $orgId)
            ->where('uploaded_by', $uploaderId)
            ->where('session_id', $validated['session_id'])
            ->count();

        if ($uploadedPagesCount >= $validated['total_pages']) {
            // Queue consolidation job
            ConsolidateAnswersJob::dispatch($validated['session_id'], $orgId);
            Log::info("Dispatched ConsolidateAnswersJob for session {$validated['session_id']}");
        }

        return response()->json([
            'page' => $this->pagePayload($request, $page),
            'progress' => [
                'uploaded' => $uploadedPagesCount,
                'total' => $validated['total_pages'],
                'is_complete' => $uploadedPagesCount >= $validated['total_pages'],
            ],
        ], 201);
    }

    public function pageImage(Request $request, OmrScanPage $page): StreamedResponse
    {
        abort_unless(
            (int) $page->organization_id === (int) $request->user()->organization_id,
            404
        );

        $disk = Storage::disk('local');
        if (! $disk->exists($page->image_path)) {
            $disk = Storage::disk('public');
        }
        abort_unless($page->image_path && $disk->exists($page->image_path), 404);

        return $disk->response($page->image_path, basename($page->image_path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function duplicatePageResponse(Request $request, OmrScanPage $page, string $requestFingerprint)
    {
        if ($page->request_fingerprint && ! hash_equals($page->request_fingerprint, $requestFingerprint)) {
            return response()->json([
                'error' => 'A chave de idempotência já foi usada com outro conteúdo.',
                'code' => 'IDEMPOTENCY_CONFLICT',
            ], 409);
        }

        $uploaded = OmrScanPage::query()
            ->where('organization_id', $page->organization_id)
            ->where('uploaded_by', $page->uploaded_by)
            ->where('session_id', $page->session_id)
            ->count();

        return response()->json([
            'page' => $this->pagePayload($request, $page),
            'duplicate' => true,
            'progress' => [
                'uploaded' => $uploaded,
                'total' => $page->total_pages,
                'is_complete' => $uploaded >= $page->total_pages,
            ],
        ], 200);
    }

    private function pagePayload(Request $request, OmrScanPage $page): array
    {
        $version = $request->is('api/v2/*') ? 'v2' : 'v1';
        $payload = $page->makeHidden(['image_path', 'request_fingerprint'])->toArray();
        $payload['image_url'] = url("/api/{$version}/omr/scans/pages/{$page->id}/image");

        return $payload;
    }

    /** Lists keep their order; JSON objects are sorted recursively for stable retries. */
    private function canonicalizeFingerprintValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalizeFingerprintValue($item), $value);
        }

        ksort($value, SORT_STRING);

        return array_map(fn (mixed $item): mixed => $this->canonicalizeFingerprintValue($item), $value);
    }

    /**
     * Machine evidence may only auto-grade after every structural gate passed.
     * Human evidence is a separate, explicit path and must resolve every item.
     */
    private function assertProcessingEvidenceIsConsistent(array $evidence): void
    {
        $action = $evidence['action'] ?? null;
        $path = $evidence['processingPath'] ?? null;
        $questions = $evidence['questions'] ?? [];
        $questionActions = array_column($questions, 'action');
        $allQuestionsAccepted = $questionActions !== []
            && count(array_filter($questionActions, fn (mixed $value): bool => $value !== 'accept')) === 0;
        $machineGatesPassed = data_get($evidence, 'imageQuality.acceptable') === true
            && (int) data_get($evidence, 'geometry.fiducialCount', 0) === 4
            && is_numeric(data_get($evidence, 'geometry.reprojectionError.max'))
            && (float) data_get($evidence, 'geometry.reprojectionError.max') <= 2.0;
        $machineGatesPassed = $machineGatesPassed
            && is_numeric(data_get($evidence, 'geometry.orientationDegrees'))
            && abs((float) data_get($evidence, 'geometry.orientationDegrees')) <= 20.0
            && is_numeric(data_get($evidence, 'geometry.scaleRatio'))
            && (float) data_get($evidence, 'geometry.scaleRatio') >= 0.5
            && (float) data_get($evidence, 'geometry.scaleRatio') <= 1.05;

        if ($action === 'accept' && $path === 'homography') {
            if (! $machineGatesPassed || ! $allQuestionsAccepted) {
                throw ValidationException::withMessages([
                    'processing_evidence' => 'A captura automática não comprovou todos os requisitos de qualidade e geometria.',
                ]);
            }

            return;
        }

        if ($action === 'accept' && $path === 'hybrid') {
            $hasManualResolution = collect($questions)->contains(fn (array $question): bool => in_array(
                'manual_confirmation',
                $question['reasons'] ?? [],
                true,
            ));
            if (! $machineGatesPassed || ! $allQuestionsAccepted || ! $hasManualResolution
                || ! in_array('manual_confirmation', $evidence['reasons'] ?? [], true)) {
                throw ValidationException::withMessages([
                    'processing_evidence' => 'A revisão híbrida não preservou os gates da máquina e as confirmações humanas.',
                ]);
            }

            return;
        }

        if ($action === 'accept' && $path === 'manual') {
            $manualReason = in_array('manual_confirmation', $evidence['reasons'] ?? [], true);
            $allQuestionsConfirmed = $allQuestionsAccepted
                && collect($questions)->every(fn (array $question): bool => in_array(
                    'manual_confirmation',
                    $question['reasons'] ?? [],
                    true,
                ));

            if (! $manualReason || ! $allQuestionsConfirmed) {
                throw ValidationException::withMessages([
                    'processing_evidence' => 'A confirmação manual deve resolver explicitamente todas as questões.',
                ]);
            }

            return;
        }

        if ($action === 'review' && $path === 'homography'
            && in_array('review', $questionActions, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'processing_evidence' => 'A ação informada contradiz a evidência de processamento.',
        ]);
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
                new ActiveOrganizationMember((int) $orgId, 'student'),
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
        if ($copy?->student_id && (int) $copy->student_id !== (int) $validated['student_id']) {
            throw ValidationException::withMessages([
                'student_id' => 'Esta cópia individualizada pertence a outro aluno.',
            ]);
        }

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
