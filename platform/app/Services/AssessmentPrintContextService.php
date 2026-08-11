<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\User;
use Carbon\Carbon;

class AssessmentPrintContextService
{
    /** @return array<string, mixed> */
    public function snapshot(Exam $exam, ?User $student = null, ?SchoolClass $schoolClass = null, ?int $copyNumber = null): array
    {
        $exam->loadMissing(['author', 'organization', 'discipline']);
        $student?->loadMissing('studentProfile');
        $dateValue = data_get($exam->settings, 'available_from') ?: $exam->created_at;
        $date = $dateValue ? Carbon::parse($dateValue) : null;

        return [
            'student' => [
                'name' => $student?->name,
                'registration' => $student?->studentProfile?->registration_number,
            ],
            'teacher' => ['name' => $exam->author?->name],
            'institution' => ['name' => $exam->organization?->name],
            'class' => ['name' => $schoolClass?->name],
            'subject' => ['name' => $exam->discipline?->name],
            'assessment' => [
                'title' => $exam->title,
                'subtitle' => data_get($exam->settings, 'subtitle'),
                'date' => $date?->timezone(config('app.timezone'))->format('d/m/Y'),
                'period' => data_get($exam->settings, 'period'),
                'copy_number' => $copyNumber,
            ],
        ];
    }
}
