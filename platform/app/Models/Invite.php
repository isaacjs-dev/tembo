<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invite extends Model
{
    use Auditable;

    protected $fillable = [
        'inviter_id',
        'organization_id',
        'invitee_email',
        'invitee_user_id',
        'target_role',
        'invite_type',
        'target_entity_type',
        'target_entity_id',
        'token',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Invite $invite) {
            if (empty($invite->token)) {
                $invite->token = Str::random(64);
            }
            if (empty($invite->expires_at)) {
                $invite->expires_at = now()->addDays(7);
            }
            if (empty($invite->status)) {
                $invite->status = 'pending';
            }
        });
    }

    /* ── Relationships ── */

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function inviteeUser()
    {
        return $this->belongsTo(User::class, 'invitee_user_id');
    }

    public function scopeOfType($q, string $type)
    {
        return $q->where('invite_type', $type);
    }

    /* ── Scopes ── */

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeForEmail($q, string $email)
    {
        return $q->where('invitee_email', $email);
    }

    public function scopeNotExpired($q)
    {
        return $q->where('expires_at', '>', now());
    }

    /* ── Helpers ── */

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function accept(): void
    {
        $this->update(['status' => 'accepted']);
    }

    public function decline(): void
    {
        $this->update(['status' => 'declined']);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'canceled']);
    }
}
