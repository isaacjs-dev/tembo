<?php

namespace App\Services;

use App\Models\PublicCatalogRewardRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PublicCatalogRewardScheduleService
{
    /**
     * Lock the stable subject slot and materialize any due transition.
     * Call from inside the transaction that will use the returned pointers.
     */
    public function lockAndNormalize(string $subjectKind, ?CarbonInterface $at = null): object
    {
        $at ??= now();
        $slot = DB::table('public_catalog_reward_rule_slots')
            ->where('subject_kind', $subjectKind)->lockForUpdate()->first();
        abort_unless($slot, 409, 'Slot de recompensa não configurado.');

        if (! $slot->scheduled_rule_id) {
            return $slot;
        }

        $scheduled = PublicCatalogRewardRule::query()->lockForUpdate()->find($slot->scheduled_rule_id);
        if (! $scheduled || $scheduled->status !== 'scheduled') {
            return $this->updateSlot($slot, ['scheduled_rule_id' => null]);
        }
        if ($scheduled->ends_at && $scheduled->ends_at->isBefore($at)) {
            $scheduled->update(['status' => 'retired', 'retired_at' => $at]);

            return $this->updateSlot($slot, ['scheduled_rule_id' => null]);
        }
        if ($scheduled->starts_at && $scheduled->starts_at->isAfter($at)) {
            return $slot;
        }

        if ($slot->active_rule_id && (int) $slot->active_rule_id !== (int) $scheduled->id) {
            PublicCatalogRewardRule::query()->lockForUpdate()->find($slot->active_rule_id)?->update([
                'status' => 'retired', 'retired_at' => $at,
            ]);
        }
        $scheduled->update(['status' => 'active', 'retired_at' => null]);

        return $this->updateSlot($slot, [
            'active_rule_id' => $scheduled->id,
            'scheduled_rule_id' => null,
        ]);
    }

    private function updateSlot(object $slot, array $changes): object
    {
        DB::table('public_catalog_reward_rule_slots')->where('subject_kind', $slot->subject_kind)->update([
            ...$changes, 'updated_at' => now(),
        ]);

        foreach ($changes as $key => $value) {
            $slot->{$key} = $value;
        }

        return $slot;
    }
}
