<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\RevisionAttempt;
use App\Models\StudentGamificationProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GamificationService
{
    public function reward(RevisionAttempt $attempt): StudentGamificationProfile
    {
        return DB::transaction(function () use ($attempt): StudentGamificationProfile {
            $attempt = RevisionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            abort_unless($attempt->status === 'completed', 409);
            User::query()->whereKey($attempt->student_id)->lockForUpdate()->firstOrFail();
            $profile = StudentGamificationProfile::query()
                ->where('organization_id', $attempt->organization_id)
                ->where('student_id', $attempt->student_id)
                ->lockForUpdate()->first();
            if ($attempt->rewarded_at) {
                abort_unless($profile, 409, 'Perfil de gamificação não encontrado para recompensa já processada.');

                return $profile;
            }

            $profile ??= new StudentGamificationProfile(['organization_id' => $attempt->organization_id, 'student_id' => $attempt->student_id]);
            $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
            $xp = max(10, (int) round((float) $attempt->score * 10));
            $previous = $profile->last_study_date ? CarbonImmutable::parse($profile->last_study_date) : null;
            if (! $previous || ! $previous->isSameDay($today)) {
                $profile->current_streak = $previous?->isSameDay($today->subDay()) ? ((int) $profile->current_streak + 1) : 1;
            }
            $profile->xp = (int) $profile->xp + $xp;
            $profile->level = intdiv((int) $profile->xp, 100) + 1;
            $profile->longest_streak = max((int) $profile->longest_streak, (int) $profile->current_streak);
            $profile->last_study_date = $today;
            $profile->save();
            $attempt->update(['xp_earned' => $xp, 'rewarded_at' => now()]);
            $this->awardBadges($attempt, $profile);

            return $profile;
        }, 3);
    }

    private function awardBadges(RevisionAttempt $attempt, StudentGamificationProfile $profile): void
    {
        $definitions = [
            'first_revision' => ['name' => 'Primeira revisão', 'description' => 'Concluiu a primeira revisão.', 'icon' => 'school'],
            'streak_3' => ['name' => 'Sequência de 3 dias', 'description' => 'Estudou por três dias seguidos.', 'icon' => 'local_fire_department'],
            'level_5' => ['name' => 'Nível 5', 'description' => 'Alcançou o nível 5.', 'icon' => 'workspace_premium'],
        ];
        foreach ($definitions as $key => $definition) {
            $eligible = $key === 'first_revision' || ($key === 'streak_3' && $profile->current_streak >= 3) || ($key === 'level_5' && $profile->level >= 5);
            if (! $eligible) {
                continue;
            }
            $badge = Badge::firstOrCreate(['organization_id' => $attempt->organization_id, 'key' => $key], [...$definition, 'criteria' => ['system' => true]]);
            $badge->students()->syncWithoutDetaching([$attempt->student_id => ['revision_attempt_id' => $attempt->id, 'awarded_at' => now(), 'metadata' => json_encode(['xp' => $profile->xp, 'level' => $profile->level])]]);
        }
    }
}
