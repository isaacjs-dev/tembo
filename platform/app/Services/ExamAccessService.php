<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExamAccessService
{
    private const SESSION_GRANTS_KEY = 'student_exam_grants';

    public function __construct(private ExamApplicationModeService $applicationModes) {}

    public function findForStudent(User $student, int|string $examId): Exam
    {
        return Exam::query()
            ->where('organization_id', $student->organization_id)
            ->findOrFail($examId);
    }

    public function ensureJoinable(Exam $exam, User $student): void
    {
        $this->ensureSameOrganization($exam, $student);
        $this->ensureActiveWindow($exam);
    }

    public function ensureCanAccess(
        Exam $exam,
        User $student,
        Request $request,
        string $context = 'active'
    ): void {
        $this->ensureSameOrganization($exam, $student);

        if (
            ! $this->isEnrolled($exam, $student)
            && ! $this->hasSessionGrant($exam, $request)
            && ! $this->hasOwnedSubmission($exam, $student)
        ) {
            throw new AuthorizationException('Esta avaliação não foi disponibilizada para você.');
        }

        if ($context === 'results') {
            $this->ensureResultsWindow($exam);

            return;
        }

        $this->ensureActiveWindow($exam);

        if ($context !== 'overview' && ! $this->supportsOnline($exam)) {
            throw new HttpException(403, 'Esta avaliação foi configurada somente para aplicação impressa.');
        }
    }

    public function grantFromAccessCode(Exam $exam, Request $request): void
    {
        $grants = $request->session()->get(self::SESSION_GRANTS_KEY, []);
        $grants[(string) $exam->id] = [
            'code_hash' => $this->accessCodeHash($exam),
            'granted_at' => now()->toIso8601String(),
        ];

        $request->session()->put(self::SESSION_GRANTS_KEY, $grants);
    }

    /**
     * @return array<int>
     */
    public function grantedExamIds(Request $request): array
    {
        return collect($request->session()->get(self::SESSION_GRANTS_KEY, []))
            ->keys()
            ->filter(fn ($id) => ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function isEnrolled(Exam $exam, User $student): bool
    {
        return $exam->students()->where('users.id', $student->id)->exists()
            || $exam->schoolClasses()
                ->whereHas('students', fn ($query) => $query->where('users.id', $student->id))
                ->exists();
    }

    public function hasSessionGrant(Exam $exam, Request $request): bool
    {
        $grant = $request->session()->get(self::SESSION_GRANTS_KEY.'.'.$exam->id);

        if (! is_array($grant) || ! is_string($grant['code_hash'] ?? null) || empty($exam->access_code)) {
            return false;
        }

        return hash_equals($this->accessCodeHash($exam), $grant['code_hash']);
    }

    public function hasOwnedSubmission(Exam $exam, User $student): bool
    {
        return $exam->submissions()
            ->where('user_id', $student->id)
            ->exists();
    }

    /**
     * @return array{state: string, opens_at: ?CarbonImmutable, closes_at: ?CarbonImmutable}
     */
    public function availability(Exam $exam): array
    {
        $settings = is_array($exam->settings) ? $exam->settings : [];
        $opensAt = $this->parseDate($settings['available_from'] ?? null);
        $closesAt = $this->parseDate($settings['available_until'] ?? null);
        $now = CarbonImmutable::now();

        $state = 'open';
        if ($opensAt && $now->isBefore($opensAt)) {
            $state = 'upcoming';
        } elseif ($closesAt && ! $now->isBefore($closesAt)) {
            $state = 'closed';
        }

        return [
            'state' => $state,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
        ];
    }

    /**
     * The legacy show_results flag released both the score and the key.
     *
     * @return array{show_score: bool, show_answers: bool, show_feedback: bool}
     */
    public function releaseSettings(Exam $exam): array
    {
        $settings = is_array($exam->settings) ? $exam->settings : [];
        $legacy = filter_var($settings['show_results'] ?? false, FILTER_VALIDATE_BOOL);
        $releaseAt = $this->parseDate($settings['results_available_from'] ?? null);

        if ($releaseAt && CarbonImmutable::now()->isBefore($releaseAt)) {
            return [
                'show_score' => false,
                'show_answers' => false,
                'show_feedback' => false,
            ];
        }

        return [
            'show_score' => array_key_exists('show_score', $settings)
                ? filter_var($settings['show_score'], FILTER_VALIDATE_BOOL)
                : $legacy,
            'show_answers' => array_key_exists('show_answers', $settings)
                ? filter_var($settings['show_answers'], FILTER_VALIDATE_BOOL)
                : $legacy,
            'show_feedback' => array_key_exists('show_feedback', $settings)
                ? filter_var($settings['show_feedback'], FILTER_VALIDATE_BOOL)
                : $legacy,
        ];
    }

    public function hasReleasedResults(Exam $exam): bool
    {
        return in_array(true, $this->releaseSettings($exam), true);
    }

    public function applicationMode(Exam $exam): string
    {
        return $this->applicationModes->mode($exam);
    }

    public function supportsOnline(Exam $exam): bool
    {
        return $this->applicationModes->capabilities($exam)['digital'];
    }

    public function resultsAvailableAt(Exam $exam): ?CarbonImmutable
    {
        return $this->parseDate($exam->settings['results_available_from'] ?? null);
    }

    private function ensureSameOrganization(Exam $exam, User $student): void
    {
        if (! $student->organization_id || (int) $exam->organization_id !== (int) $student->organization_id) {
            throw new AuthorizationException('Esta avaliação não pertence à sua instituição.');
        }
    }

    private function ensureActiveWindow(Exam $exam): void
    {
        if ($exam->status !== 'published') {
            throw new HttpException(403, 'Esta avaliação não está aberta.');
        }

        $availability = $this->availability($exam);
        if ($availability['state'] === 'upcoming') {
            throw new HttpException(403, 'Esta avaliação ainda não está disponível.');
        }
        if ($availability['state'] === 'closed') {
            throw new HttpException(403, 'O prazo desta avaliação foi encerrado.');
        }
    }

    private function ensureResultsWindow(Exam $exam): void
    {
        if (! in_array($exam->status, ['published', 'closed'], true)) {
            throw new HttpException(403, 'Os resultados desta avaliação não estão disponíveis.');
        }

        if ($this->availability($exam)['state'] === 'upcoming') {
            throw new HttpException(403, 'Esta avaliação ainda não está disponível.');
        }
    }

    private function accessCodeHash(Exam $exam): string
    {
        return hash('sha256', (string) $exam->access_code);
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
