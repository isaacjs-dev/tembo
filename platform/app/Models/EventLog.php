<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    protected $fillable = [
        'organization_id',
        'actor_user_id',
        'event_code',
        'severity',
        'entity_type',
        'entity_id',
        'message',
        'context_json',
        'before_json',
        'after_json',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'context_json' => 'array',
        'before_json' => 'array',
        'after_json' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
