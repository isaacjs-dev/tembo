<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmOmrScanRequest;
use App\Http\Requests\StoreOmrScanRequest;
use App\Http\Requests\UpdateOmrScanLocalRequest;
use App\Models\Exam;
use App\Models\ExamCopy;
use App\Models\OmrAuditLog;
use App\Models\OmrScan;
use App\Models\OmrScanPage;
use App\Models\OmrTemplate;
use App\Models\User;
use App\Services\OmrGradingService;
use App\Services\QrCodeSigningService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OmrController extends Controller
{
    public function __construct(
        private OmrGradingService $gradingService
    ) {}

    /**
     * List all scans for the organization
     */
    public function index(Request $request)
    {
        $orgId = auth()->user()->organization_id;

        $query = OmrScan::where('organization_id', $orgId)
            ->with(['exam', 'student', 'uploader']);

        // Filtro por status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtro por exam
        if ($request->filled('exam_id')) {
            $query->where('exam_id', $request->exam_id);
        }

        $scans = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $exams = Exam::where('organization_id', $orgId)->orderBy('title')->get();

        // Contadores para badges
        $reviewCount = OmrScan::where('organization_id', $orgId)->where('status', 'reviewing')->count();
        $pendingCount = OmrScan::where('organization_id', $orgId)->where('status', 'pending')->count();
        $confirmedCount = OmrScan::where('organization_id', $orgId)->where('status', 'confirmed')->count();
        $totalCount = OmrScan::where('organization_id', $orgId)->count();

        return view('omr.index', compact('scans', 'exams', 'reviewCount', 'pendingCount', 'confirmedCount', 'totalCount'));
    }

    /**
     * Show upload form
     */
    public function create()
    {
        $orgId = auth()->user()->organization_id;
        $exams = Exam::where('organization_id', $orgId)
            ->where('status', 'published')
            ->orderBy('title')
            ->get();

        $students = User::query()
            ->memberOfOrganization((int) $orgId, 'student')
            ->orderBy('name')
            ->get();

        return view('omr.upload', compact('exams', 'students'));
    }

    /**
     * Store uploaded scan with idempotency
     */
    public function store(StoreOmrScanRequest $request)
    {
        DB::beginTransaction();
        try {
            $orgId = auth()->user()->organization_id;
            $file = $request->file('image');

            // Generate idempotency key from file hash
            $idempotencyKey = implode(':', [
                'web',
                $orgId,
                $request->integer('exam_id'),
                $request->integer('copy_id'),
                hash_file('sha256', $file->getRealPath()),
            ]);

            // Duplicata (mesma imagem):
            //  - Já CONFIRMADO → mostra o resultado (bloqueado, não relê).
            //  - Ainda em conferência → descarta e REFAZ a leitura com o motor atual
            //    (evita mostrar uma leitura antiga, ex.: feita antes de uma melhoria do motor).
            $existing = OmrScan::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                DB::rollBack();

                return redirect()->route('institution.omr.review', $existing->id)
                    ->with('status', 'Esta imagem já foi recebida. O resultado existente foi preservado.');
            }

            // Store image
            $path = $file->store('omr-scans/'.$orgId, 'local');

            $exam = Exam::withoutGlobalScopes()
                ->whereKey($request->integer('exam_id'))
                ->where('organization_id', $orgId)
                ->with('questions')
                ->firstOrFail();

            $status = 'pending';
            $detectedAnswers = [];
            $confidenceScore = 0;
            $qualityJson = null;
            $omrTemplateId = null;   // FR-13: template do cartão (do QR)
            $layoutVersion = 0;      // versão do layout usada na geração

            // Processa o payload do motor OMR (navegador). Leitura 100% baseada nos
            // marcadores + geometria do QR — sem depender de OmrTemplate por prova.
            if ($request->filled('omr_payload')) {
                $payload = json_decode($request->omr_payload, true);
                if ($payload) {
                    $qr = $payload['qrData'] ?? null;
                    $copy = $request->filled('copy_id')
                        ? ExamCopy::query()->whereKey($request->integer('copy_id'))->where('exam_id', $exam->id)->firstOrFail()
                        : null;

                    if ((array_key_exists('qrData', $payload) || $copy) && ! is_array($qr)) {
                        return $this->rejectScan($path ?? null, 'O QR Code da cópia não pôde ser validado. Faça uma nova leitura do cartão original.');
                    }

                    // Missing version/signature never enters a permissive legacy fallback.
                    if (is_array($qr)) {
                        // 1) Assinatura HMAC (anti-adulteração).
                        $signer = app(QrCodeSigningService::class);
                        if (! $signer->hasSupportedContract($qr) || ! $signer->verifyPayload($qr, $orgId)) {
                            return $this->rejectScan($path ?? null, 'Cartão inválido: a assinatura do QR Code não confere (possível adulteração ou organização incorreta).');
                        }
                        // 2) Integridade: o cartão pertence a ESTA prova e cópia?
                        if ((int) $qr['e'] !== (int) $exam->id) {
                            return $this->rejectScan($path ?? null, 'Este cartão pertence a outra avaliação (o QR não confere com a prova selecionada).');
                        }
                        if (! $copy || (int) $qr['c'] !== (int) $copy->id || ! hash_equals((string) $copy->validation_hash, (string) $qr['h'])) {
                            return $this->rejectScan($path ?? null, 'Este cartão não pertence à cópia identificada (hash de validação não confere).');
                        }
                    }

                    // 3) FR-13: vincula a leitura ao TEMPLATE + versão do layout (auditoria/integridade).
                    if (is_array($qr) && ! empty($qr['tpl_id'])) {
                        $tpl = OmrTemplate::query()
                            ->whereKey((int) $qr['tpl_id'])
                            ->where(function ($query) use ($orgId) {
                                $query->where('organization_id', $orgId)
                                    ->orWhere('is_system', true)
                                    ->orWhere('visibility_scope', 'system');
                            })
                            ->first();
                        if (! $tpl) {
                            return $this->rejectScan($path ?? null, 'O template indicado pelo QR Code não está disponível nesta organização.');
                        }
                        $omrTemplateId = $tpl->id;
                        $layoutVersion = (int) $qr['tpl_v'];
                    }

                    $quality = $payload['quality'] ?? ['needs_review' => true, 'confidence' => 0];
                    $confidenceScore = $quality['confidence'] ?? 0;
                    // Sempre passa pela conferência humana: o motor não sabe o aluno e a nota
                    // só é gerada ao confirmar com o aluno selecionado.
                    $status = 'reviewing';

                    // Ordem IMPRESSA das questões = questions_map da cópia (a folha pode estar
                    // embaralhada). Fallback: ordem da prova quando não há cópia identificada.
                    $printedOrder = ($copy && is_array($copy->questions_map) && count($copy->questions_map) > 0)
                        ? array_values($copy->questions_map)                 // [question_id, ...] na ordem da folha
                        : $exam->questions->pluck('id')->values()->all();

                    // Confiança por questão + contagem por status (para a conferência colorir).
                    $statusConfidence = ['OK' => 0.95, 'UNCERTAIN' => 0.50, 'DOUBLE' => 0.40, 'BLANK' => 0.55];
                    $confidences = [];
                    $counts = ['ok' => 0, 'uncertain' => 0, 'double' => 0, 'blank' => 0];

                    foreach (($payload['answers'] ?? []) as $res) {
                        $pos = (int) ($res['q'] ?? 0); // posição impressa, 1-based
                        if ($pos < 1 || ! isset($printedOrder[$pos - 1])) {
                            continue;
                        }
                        $questionId = $printedOrder[$pos - 1];

                        // Guardar a resposta em ESPAÇO VISUAL como ÍNDICE (0=A, 1=B, ...).
                        // O motor retorna o rótulo da bolha marcada (ou null = branco).
                        // OmrGradingService::grade() faz o reverse-map visual->original via options_map.
                        $sel = $res['selected'] ?? null;
                        if ($sel === null || $sel === '') {
                            $visualIdx = null;
                        } elseif (is_numeric($sel)) {
                            $visualIdx = (int) $sel;
                        } else {
                            $visualIdx = ord(strtoupper((string) $sel)) - 65; // 'A'->0
                        }
                        $detectedAnswers[$questionId] = $visualIdx;

                        $st = strtoupper($res['status'] ?? 'OK');
                        $confidences[$questionId] = $statusConfidence[$st] ?? 0.9;
                        $key = match ($st) {
                            'UNCERTAIN' => 'uncertain',
                            'DOUBLE' => 'double',
                            'BLANK' => 'blank',
                            default => 'ok',
                        };
                        $counts[$key]++;
                    }

                    // VALIDAÇÃO DE COERÊNCIA (offline + anti-divergência):
                    // O QR carrega o gabarito OFICIAL em ESPAÇO VISUAL (a ordem REAL impressa,
                    // já com embaralhamento — gab_enc cifrado). Comparamos com o gabarito oficial
                    // preservado na cópia (correct_option + options_map), com fallback ao banco
                    // para cartões legados. Se o QR carregar uma chave que CONTRADIZ a oficial, marcamos
                    // a questão como erro de validação e bloqueamos a correção automática dela —
                    // até análise manual. Vale para qualquer tipo, inclusive V/F (A/B na ordem real).
                    $validationErrors = [];
                    if (is_array($qr) && ! empty($qr['gab_enc']) && $copy && is_array($copy->options_map)) {
                        $qrGab = app(QrCodeSigningService::class)
                            ->decryptGabarito($qr['gab_enc'], $orgId); // [visual,...] na ordem qs..qe
                        if (is_array($qrGab)) {
                            $snapshotById = collect($copy->question_snapshot ?? [])
                                ->keyBy(fn (array $item): int => (int) $item['id']);
                            $qsBase = (int) ($qr['qs'] ?? 1);
                            foreach ($qrGab as $j => $qrVisual) {
                                $pos = $qsBase + (int) $j; // posição impressa, 1-based
                                if (! isset($printedOrder[$pos - 1])) {
                                    continue;
                                }
                                $qid = $printedOrder[$pos - 1];
                                $question = $exam->questions->firstWhere('id', $qid);
                                $snapshot = $snapshotById->get((int) $qid);
                                if (! $question && ! is_array($snapshot)) {
                                    continue;
                                }
                                $optMap = $copy->options_map[$qid] ?? null;
                                $co = is_array($snapshot)
                                    ? data_get($snapshot, 'content.correct_option')
                                    : ($question->content['correct_option'] ?? null);
                                $dbVisual = (is_array($optMap) && $co !== null && $co !== '')
                                    ? (($v = array_search($co, $optMap)) !== false ? (int) $v : null)
                                    : null;
                                $qrV = ($qrVisual === -1 || $qrVisual === null) ? null : (int) $qrVisual;
                                // Erro só quando o QR TEM chave e ela DIFERE da versão impressa.
                                if ($qrV !== null && $qrV !== $dbVisual) {
                                    $validationErrors[$qid] = ['qr' => $qrV, 'oficial' => $dbVisual];
                                }
                            }
                        }
                    }

                    $qualityJson = array_merge($quality, [
                        'confidences' => $confidences,
                        'ok_count' => $counts['ok'],
                        'uncertain_count' => $counts['uncertain'],
                        'double_count' => $counts['double'],
                        'blank_count' => $counts['blank'],
                        'validation_errors' => $validationErrors,
                    ]);
                }
            }

            // Criar scan
            $scan = OmrScan::create([
                'exam_id' => $request->exam_id,
                'organization_id' => $orgId,
                'uploaded_by' => auth()->id(),
                'image_path' => $path,
                'idempotency_key' => $idempotencyKey,
                'status' => $status,
                'student_id' => $request->student_id,
                'copy_id' => $request->copy_id,
                'omr_template_id' => $omrTemplateId,   // FR-13: leitura vinculada ao template
                'layout_version' => $layoutVersion,
                'detected_answers' => $detectedAnswers,
                'confidence_score' => $confidenceScore,
                'quality_json' => $qualityJson,
                'layout_meta' => $request->filled('layout_meta') ? json_decode($request->layout_meta, true) : null,
            ]);

            DB::commit();

            // Sempre vai para a conferência: o usuário confere as respostas lidas,
            // seleciona o aluno e confirma para gerar a nota.
            return redirect()->route('institution.omr.review', $scan->id)
                ->with('status', 'Gabarito lido. Confira as respostas, selecione o aluno e confirme para gerar a nota.');

        } catch (QueryException $e) {
            DB::rollBack();
            if (isset($path)) {
                Storage::disk('local')->delete($path);
            }

            $existing = isset($idempotencyKey)
                ? OmrScan::where('idempotency_key', $idempotencyKey)->first()
                : null;
            if ($existing) {
                return redirect()->route('institution.omr.review', $existing->id)
                    ->with('status', 'Esta imagem já foi recebida. O resultado existente foi preservado.');
            }

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao salvar scan OMR: '.$e->getMessage(), [
                'exception' => $e,
                'user_id' => auth()->id(),
            ]);

            if (isset($path)) {
                Storage::disk('local')->delete($path);
            }

            return back()->withErrors(['error' => 'Ocorreu um erro ao processar o scan. Por favor, tente novamente.'])
                ->withInput();
        }
    }

    /** Aborta o scan: rollback da transação, remove a imagem e volta com erro. */
    private function rejectScan(?string $path, string $message)
    {
        DB::rollBack();
        if ($path) {
            Storage::disk('local')->delete($path);
        }

        return back()->withErrors(['error' => $message])->withInput();
    }

    /**
     * Show review/manual confirmation page
     */
    public function review(string $id)
    {
        $orgId = auth()->user()->organization_id;

        $scan = OmrScan::where('organization_id', $orgId)
            ->with(['exam.questions', 'student', 'uploader', 'pages', 'copy', 'omrTemplate'])
            ->findOrFail($id);

        // Alunos da organização (FK legado OU pivot user_organization ativa).
        $students = User::query()
            ->memberOfOrganization((int) $orgId, 'student')
            ->orderBy('name')
            ->get();

        // Gabarito por questão em ESPAÇO VISUAL (mesma convenção das respostas detectadas/
        // confirmadas): correct_option (índice original) -> posição visual via options_map da
        // cópia. Permite à conferência destacar acertos (verde) e erros (vermelho).
        $optionsMap = $scan->copy?->options_map ?? [];
        $correctAnswers = [];
        foreach ($scan->exam->questions as $q) {
            $correctOriginal = $q->content['correct_option'] ?? null;
            $map = $optionsMap[$q->id] ?? null;
            if (is_array($map) && $correctOriginal !== null && $correctOriginal !== '') {
                $cv = array_search($correctOriginal, $map);
                $correctAnswers[$q->id] = $cv !== false ? (int) $cv : null;
            } else {
                $correctAnswers[$q->id] = null;
            }
        }

        return view('omr.review', compact('scan', 'students', 'correctAnswers'));
    }

    public function image(Request $request, OmrScan $scan, string $variant = 'original'): StreamedResponse
    {
        abort_unless((int) $scan->organization_id === (int) $request->user()->organization_id, 404);

        $path = match ($variant) {
            'warped' => $scan->warped_path,
            'debug' => $scan->debug_path,
            default => $scan->image_path,
        };

        return $this->privateOrHistoricalImage($path);
    }

    public function pageImage(Request $request, OmrScan $scan, OmrScanPage $page): StreamedResponse
    {
        abort_unless(
            (int) $scan->organization_id === (int) $request->user()->organization_id
            && (int) $page->organization_id === (int) $scan->organization_id
            && (string) $page->session_id === (string) $scan->session_id,
            404
        );

        return $this->privateOrHistoricalImage($page->image_path);
    }

    /**
     * Store confirmed answers and optionally grade
     */
    public function confirm(ConfirmOmrScanRequest $request, string $id)
    {
        $orgId = auth()->user()->organization_id;

        $scan = OmrScan::where('organization_id', $orgId)
            ->with('exam.questions')
            ->findOrFail($id);
        $copy = $scan->copy_id
            ? ExamCopy::query()->whereKey($scan->copy_id)->where('exam_id', $scan->exam_id)->first()
            : null;
        if ($copy?->student_id && (int) $copy->student_id !== (int) $request->student_id) {
            throw ValidationException::withMessages([
                'student_id' => 'Esta cópia individualizada pertence a outro aluno.',
            ]);
        }

        // Sanitizar respostas: converter strings vazias para null
        $sanitizedAnswers = collect($request->answers)->map(function ($value) {
            if ($value === '' || $value === '—') {
                return null;
            }

            return $value;
        })->toArray();

        $previousData = [
            'confirmed_answers' => $scan->confirmed_answers,
            'student_id' => $scan->student_id,
            'score' => $scan->score,
        ];

        $scan->update([
            'student_id' => $request->student_id,
            'confirmed_answers' => $sanitizedAnswers,
            'status' => 'confirmed',
            'confidence_score' => 1.0, // manual = 100% confidence
        ]);

        OmrAuditLog::create([
            'omr_scan_id' => $scan->id,
            'user_id' => auth()->id(),
            'action' => 'MANUAL_CORRECTION',
            'previous_data' => $previousData,
            'new_data' => [
                'confirmed_answers' => $sanitizedAnswers,
                'student_id' => $request->student_id,
            ],
        ]);

        // Auto-grade if student is assigned
        if ($request->student_id) {
            $scoreResult = $this->gradingService->grade($scan, $sanitizedAnswers, $copy);

            $scan->update([
                'score' => $scoreResult['score'],
                'total_points' => $scoreResult['total_points'],
                'grading_details' => $scoreResult['details'],
            ]);
        }

        return redirect()->route('institution.omr.index')
            ->with('status', 'Scan confirmado e notas processadas com sucesso!');
    }

    /**
     * Reject a scan
     */
    public function reject(Request $request, string $id)
    {
        $orgId = auth()->user()->organization_id;
        $scan = OmrScan::where('organization_id', $orgId)->findOrFail($id);

        // Limpeza de arquivos físicos
        $this->cleanupScanFiles($scan);

        $scan->update([
            'status' => 'rejected',
            'notes' => $request->input('notes', 'Rejeitado pelo professor.'),
            'image_path' => null,
            'warped_path' => null,
            'debug_path' => null,
        ]);

        return redirect()->route('institution.omr.index')
            ->with('status', 'Scan rejeitado e arquivos removidos com sucesso.');
    }

    /**
     * Show webcam scan interface
     */
    public function webscan()
    {
        $orgId = auth()->user()->organization_id;

        // Mesmas avaliações listadas em /exams (ExamController@index): da organização,
        // autoradas pelo usuário logado, em qualquer status (draft/published/closed).
        $exams = Exam::where('organization_id', $orgId)
            ->where('author_id', auth()->id())
            ->orderBy('title')
            ->get();

        // Mesmos alunos listados em /institution/students (StudentController@index):
        // vinculados por FK legado OU pela pivot user_organization ativa.
        $students = User::query()
            ->memberOfOrganization((int) $orgId, 'student')
            ->orderBy('name')
            ->get();

        return view('omr.webscan', compact('exams', 'students'));
    }

    /**
     * Tela de DEBUG da leitura OMR: mostra o que o motor enxergou (marcadores, warp,
     * ROIs e preenchimento) — para diagnosticar a detecção sem depender de palpite.
     */
    public function debug()
    {
        return view('omr.debug');
    }

    /**
     * Update locally processed scan result from OmrEngine TS Core (Drag & Drop Corners)
     */
    public function updateLocal(UpdateOmrScanLocalRequest $request, string $id)
    {
        $orgId = auth()->user()->organization_id;
        $scan = OmrScan::where('organization_id', $orgId)->findOrFail($id);

        $results = json_decode($request->answers_json, true) ?? [];
        $quality = json_decode($request->quality_json, true) ?? [];
        abort_unless(is_array($results) && is_array($quality), 422);

        // Map results "q" (1-based index) to actual Exam Question ID
        // The Physical Position 'q' corresponds to the index in the ordered_questions of the copy
        $copy = $scan->copy_id ? ExamCopy::find($scan->copy_id) : null;
        $questionsMap = $copy ? $copy->questions_map : $scan->exam->questions()->orderBy('exam_questions.order')->pluck('questions.id')->toArray();

        $mappedAnswers = [];

        foreach ($results as $res) {
            $qIndex = ($res['q'] ?? 1) - 1;
            if (isset($questionsMap[$qIndex])) {
                $questionId = $questionsMap[$qIndex];
                // Get selected option index as string or null
                $mappedAnswers[$questionId] = $res['selected'] ?? null;
            }
        }

        $updateData = [
            'detected_answers' => $mappedAnswers,
            // Reprocessing never constitutes the final human confirmation.
            'status' => 'reviewing',
            'confidence_score' => max(0, min(1, (float) ($quality['overall_confidence'] ?? 0))),
        ];

        if ($request->filled('warped_image')) {
            $imageData = $request->input('warped_image');
            if (preg_match('/^data:image\/(png|jpe?g|webp);base64,/', $imageData, $type)) {
                $imageData = substr($imageData, strpos($imageData, ',') + 1);
                $decoded = base64_decode($imageData, true);
                $imageInfo = $decoded !== false ? @getimagesizefromstring($decoded) : false;
                if ($decoded !== false && $imageInfo !== false) {
                    $extension = match ($imageInfo['mime'] ?? '') {
                        'image/png' => 'png',
                        'image/jpeg' => 'jpg',
                        'image/webp' => 'webp',
                        default => null,
                    };
                    abort_unless($extension, 422);
                    $filename = 'omr-scans/'.$orgId.'/'.$scan->id.'_warped.'.$extension;
                    Storage::disk('local')->put($filename, $decoded);
                    $updateData['warped_path'] = $filename;
                }
            }
        }

        $previousData = [
            'detected_answers' => $scan->detected_answers,
            'status' => $scan->status,
            'confidence_score' => $scan->confidence_score,
            'warped_path' => $scan->warped_path,
        ];
        $scan->update($updateData);
        OmrAuditLog::create([
            'omr_scan_id' => $scan->id,
            'user_id' => auth()->id(),
            'action' => 'REPROCESSED_FOR_REVIEW',
            'previous_data' => $previousData,
            'new_data' => $updateData,
        ]);

        return response()->json(['success' => true, 'status' => 'reviewing']);
    }

    /**
     * Batch update for OMR scans
     */
    public function batchUpdate(Request $request)
    {
        $orgId = auth()->user()->organization_id;

        $request->validate([
            'scan_ids' => 'required|array',
            'scan_ids.*' => 'exists:omr_scans,id',
            'action' => 'required|in:confirm,reject,delete',
        ]);

        $scans = OmrScan::where('organization_id', $orgId)
            ->whereIn('id', $request->scan_ids)
            ->get();

        $count = 0;

        foreach ($scans as $scan) {
            if ($request->action === 'delete') {
                // Cleanup files before delete
                $this->cleanupScanFiles($scan);
                $scan->delete();
                $count++;
            } elseif ($request->action === 'reject') {
                // Cleanup files and update status
                $this->cleanupScanFiles($scan);
                $scan->update([
                    'status' => 'rejected',
                    'image_path' => null,
                    'warped_path' => null,
                    'debug_path' => null,
                ]);
                $count++;
            } elseif ($request->action === 'confirm') {
                // Confirm all without changing answers, and auto-grade if student is assigned
                $scan->update([
                    'status' => 'confirmed',
                    'confidence_score' => 1.0,
                ]);

                if ($scan->student_id) {
                    $copy = $scan->copy_id ? ExamCopy::find($scan->copy_id) : null;
                    $scoreResult = $this->gradingService->grade($scan, $scan->detected_answers ?? [], $copy);

                    $scan->update([
                        'score' => $scoreResult['score'],
                        'total_points' => $scoreResult['total_points'],
                        'grading_details' => $scoreResult['details'],
                    ]);
                }
                $count++;
            }
        }

        $message = match ($request->action) {
            'delete' => "$count gabaritos excluídos com sucesso.",
            'reject' => "$count gabaritos rejeitados.",
            'confirm' => "$count gabaritos aprovados em lote.",
        };

        return redirect()->route('institution.omr.index')->with('status', $message);
    }

    /**
     * OMR specific reports and metrics
     */
    public function reports(Request $request)
    {
        $orgId = auth()->user()->organization_id;

        $query = OmrScan::where('organization_id', $orgId);

        if ($request->filled('exam_id')) {
            $query->where('exam_id', $request->exam_id);
        }

        $totalScans = (clone $query)->count();
        $reviewingScans = (clone $query)->where('status', 'reviewing')->count();
        $confirmedScans = (clone $query)->where('status', 'confirmed')->count();
        $syncedScans = (clone $query)->where('status', 'synced')->count();
        $rejectedScans = (clone $query)->where('status', 'rejected')->count();

        // Calculate distribution of confidence scores
        $confidenceDistribution = [
            'Alto (>= 90%)' => (clone $query)->where('confidence_score', '>=', 0.90)->count(),
            'Médio (70% - 89%)' => (clone $query)->whereBetween('confidence_score', [0.70, 0.899])->count(),
            'Baixo (< 70%)' => (clone $query)->where('confidence_score', '<', 0.70)->count(),
            'Indefinido' => (clone $query)->whereNull('confidence_score')->count(),
        ];

        $exams = Exam::where('organization_id', $orgId)->orderBy('title')->get();

        return view('omr.reports', compact(
            'totalScans',
            'reviewingScans',
            'confirmedScans',
            'syncedScans',
            'rejectedScans',
            'confidenceDistribution',
            'exams'
        ));
    }

    /**
     * Helper to cleanup physical scan files
     */
    private function cleanupScanFiles(OmrScan $scan): void
    {
        foreach ([$scan->image_path, $scan->warped_path, $scan->debug_path] as $path) {
            if ($path) {
                Storage::disk('local')->delete($path);
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function privateOrHistoricalImage(?string $path): StreamedResponse
    {
        abort_if(! $path, 404);

        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            // Compatibility read only: historical scans were stored publicly.
            $disk = Storage::disk('public');
        }
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, basename($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
