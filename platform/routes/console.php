<?php

use App\Models\CourtesyGrant;
use App\Models\User;
use App\Services\MonthlyUsageService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('usage:open-month', function (MonthlyUsageService $usage) {
    $count = 0;
    User::query()->where('status', 'active')
        ->where(function ($query): void {
            $query->where('type', 'teacher')->orWhereHas('organizations', fn ($organizations) => $organizations
                ->where('user_organization.status', 'active')
                ->where('user_organization.role_in_org', 'teacher'));
        })->chunkById(200, function ($users) use ($usage, &$count): void {
            foreach ($users as $user) {
                $organizations = $user->activeOrganizations()->wherePivot('role_in_org', 'teacher')->get();
                $contexts = $organizations;
                if ($contexts->isEmpty() && $user->type === 'teacher' && ! $user->organizations()->exists()) {
                    $contexts = collect([$user->organization]);
                }
                if ($contexts->isEmpty()) {
                    continue;
                }
                foreach ($contexts as $organization) {
                    foreach (MonthlyUsageService::RESOURCES as $resource) {
                        $usage->ensurePeriod($user, $resource, organization: $organization);
                    }
                }
                $count++;
            }
        });
    $this->info("Períodos mensais abertos para {$count} professor(es).");
})->purpose('Abre os períodos mensais individuais sem apagar o histórico.');

Artisan::command('courtesies:expire', function () {
    $activated = CourtesyGrant::query()
        ->where('status', 'scheduled')
        ->where('starts_at', '<=', now())
        ->where('ends_at', '>=', now())
        ->update(['status' => 'active', 'updated_at' => now()]);
    $count = CourtesyGrant::query()
        ->whereIn('status', ['active', 'scheduled', 'suspended'])
        ->where('ends_at', '<', now())
        ->update(['status' => 'expired', 'updated_at' => now()]);
    $this->info("{$activated} cortesia(s) ativada(s) e {$count} expirada(s).");
})->purpose('Ativa e expira cortesias pelas datas sem alterar assinaturas anteriores.');

Schedule::command('usage:open-month')
    ->monthlyOn(1, '00:05')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();

Schedule::command('courtesies:expire')
    ->dailyAt('00:15')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
