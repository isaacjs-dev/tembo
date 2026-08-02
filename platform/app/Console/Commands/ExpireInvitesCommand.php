<?php

namespace App\Console\Commands;

use App\Services\InviteManagerService;
use Illuminate\Console\Command;

class ExpireInvitesCommand extends Command
{
    protected $signature = 'invites:expire';

    protected $description = 'Expira convites pendentes que passaram da data de validade';

    public function handle(InviteManagerService $service): int
    {
        $count = $service->expirePending();
        $this->info("✅ {$count} convite(s) expirado(s).");

        return self::SUCCESS;
    }
}
