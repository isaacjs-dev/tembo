<?php

namespace App\Jobs;

use App\Models\Exam;
use App\Models\ExamCopy;
use App\Models\OmrAuditLog;
use App\Models\OmrScan;
use App\Models\OmrScanPage;
use App\Services\OfflineOmrQrService;
use App\Services\OmrGradingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsolidateAnswersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $sessionId;

    public $organizationId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $sessionId, int $organizationId)
    {
        $this->sessionId = $sessionId;
        $this->organizationId = $organizationId;
    }

    /**
     * Execute the job.
     */
    public function handle(OmrGradingService $gradingService, OfflineOmrQrService $offlineQrService): void
    {
        Log::info("Starting consolidation for session: {$this->sessionId}");

        DB::beginTransaction();
        try {
            // Get all pages for this session
            $pages = OmrScanPage::where('session_id', $this->sessionId)
                ->where('organization_id', $this->organizationId)
                ->whereIn('status', ['pending', 'confirmed'])
                ->orderBy('page_index')
                ->lockForUpdate()
                ->get();

            if ($pages->isEmpty()) {
                Log::warning("No pages found for session: {$this->sessionId}");
                DB::rollBack();

                return;
            }

            $firstPage = $pages->first();
            $totalPagesExpected = $firstPage->total_pages;

            $hasMixedContext = $pages->contains(function (OmrScanPage $page) use ($firstPage) {
                return (int) $page->exam_id !== (int) $firstPage->exam_id
                    || (int) ($page->copy_id ?? 0) !== (int) ($firstPage->copy_id ?? 0)
                    || (int) ($page->student_id ?? 0) !== (int) ($firstPage->student_id ?? 0)
                    || (int) $page->total_pages !== (int) $firstPage->total_pages
                    || (int) $page->organization_id !== $this->organizationId;
            });

            if ($hasMixedContext) {
                throw new \RuntimeException('OMR session contains pages from different contexts.');
            }

            if ($pages->count() < $totalPagesExpected) {
                Log::info("Session {$this->sessionId} is incomplete. Expected {$totalPagesExpected}, got {$pages->count()}.");
                DB::rollBack();

                return;
            }

            $exam = Exam::withoutGlobalScopes()
                ->whereKey($firstPage->exam_id)
                ->where('organization_id', $this->organizationId)
                ->with('questions')
                ->firstOrFail();
            $copy = $firstPage->copy_id
                ? ExamCopy::query()->whereKey($firstPage->copy_id)->where('exam_id', $exam->id)->firstOrFail()
                : null;
            $offlineCapture = $firstPage->qr_payload !== null;
            if ($offlineCapture && ! $copy) {
                throw new \RuntimeException('Offline OMR session has no valid exam copy.');
            }

            if ($pages->contains(fn (OmrScanPage $page): bool => ($page->qr_payload !== null) !== $offlineCapture)) {
                throw new \RuntimeException('OMR session mixes online and offline QR page contracts.');
            }

            // At this point we have all pages. Let's merge answers and confidences.
            $mergedAnswers = [];
            $mergedConfidences = [];
            $totalConfidenceSum = 0;
            $totalQuestionsProcessed = 0;
            $embeddedKeyMismatches = [];
            $layoutMetadata = null;

            foreach ($pages as $page) {
                /** @var OmrScanPage $page */
                $rawA = $page->raw_answers ?? [];
                $rawC = $page->raw_confidences ?? [];

                if ($offlineCapture) {
                    /** @var array<string, mixed> $payload */
                    $payload = $page->qr_payload ?? [];
                    $metadata = $offlineQrService->validatePage(
                        $payload,
                        $this->organizationId,
                        $exam,
                        $copy,
                        (int) $page->page_index,
                        (int) $page->total_pages,
                        (int) ($payload['qs'] ?? 0),
                    );
                    $rawA = $offlineQrService->mapPrintedAnswers(
                        $copy,
                        $rawA,
                        $metadata['question_start'],
                        $metadata['question_end'],
                    );
                    $rawC = $offlineQrService->mapPrintedConfidences(
                        $copy,
                        $rawC,
                        $metadata['question_start'],
                        $metadata['question_end'],
                    );
                    $embeddedKeyMismatches += $offlineQrService->embeddedKeyMismatches(
                        $payload,
                        $this->organizationId,
                        $exam,
                        $copy,
                    );
                    $layoutMetadata ??= [
                        'qr_schema' => (int) $payload['v'],
                        'template_id' => $metadata['template_id'],
                        'template_version' => $metadata['template_version'],
                        'offline_capture' => true,
                    ];
                }

                foreach ($rawA as $qId => $opt) {
                    $mergedAnswers[$qId] = $opt;
                }
                foreach ($rawC as $qId => $conf) {
                    $mergedConfidences[$qId] = $conf;
                    $totalConfidenceSum += (float) $conf;
                    $totalQuestionsProcessed++;
                }

                // Change status so they aren't processed again
                $page->status = 'consolidated';
                $page->save();
            }

            $overallAvgConfidence = $totalQuestionsProcessed > 0
                ? ($totalConfidenceSum / $totalQuestionsProcessed)
                : 1.0;

            // The encrypted answer key is only decrypted and compared here. A card
            // whose printed key diverges from the official copy is held for review,
            // never silently auto-corrected.
            $requiresReview = $embeddedKeyMismatches !== [];
            $scoreResult = $requiresReview
                ? [
                    'score' => null,
                    'total_points' => $exam->questions->sum(fn ($question) => $question->pivot->points ?? 1),
                    'details' => ['offline_qr_key_mismatch' => $embeddedKeyMismatches],
                ]
                : $gradingService->gradeAnswers($exam->id, $copy?->id, $mergedAnswers);

            // Create the final OmrScan record
            $scan = OmrScan::create([
                'session_id' => $this->sessionId,
                'exam_id' => $firstPage->exam_id,
                'copy_id' => $firstPage->copy_id,
                'student_id' => $firstPage->student_id,
                'organization_id' => $exam->organization_id,
                'uploaded_by' => $firstPage->uploaded_by,
                'layout_version' => 1,
                'total_pages' => $totalPagesExpected,
                'image_path' => $firstPage->image_path,
                'idempotency_key' => 'session_'.$this->organizationId.'_'.$this->sessionId,
                'raw_answers' => $mergedAnswers,
                'raw_confidences' => $mergedConfidences,
                'overall_confidence' => $overallAvgConfidence,
                'score' => $scoreResult['score'],
                'total_points' => $scoreResult['total_points'],
                'grading_details' => $scoreResult['details'],
                'quality_json' => [
                    'offline_qr' => $offlineCapture,
                    'embedded_key_mismatches' => $embeddedKeyMismatches,
                    'requires_review' => $requiresReview,
                ],
                'layout_meta' => $layoutMetadata,
                'status' => $requiresReview ? 'reviewing' : 'processed',
                'source' => 'mobile',
            ]);

            // Audit
            OmrAuditLog::create([
                'omr_scan_id' => $scan->id,
                'user_id' => null, // system generated
                'action' => 'CONSOLIDATION',
                'previous_data' => null,
                'new_data' => [
                    'pages_consolidated' => $pages->count(),
                    'answers' => $mergedAnswers,
                ],
            ]);

            DB::commit();
            Log::info("Successfully consolidated session: {$this->sessionId} into scan {$scan->id}");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to consolidate session: {$this->sessionId}. ".$e->getMessage());
            throw $e;
        }
    }
}
