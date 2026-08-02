<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassOwnershipLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'school_class_id',
        'previous_owner_type',
        'previous_owner_id',
        'new_owner_type',
        'new_owner_id',
        'initiated_by',
        'transferred_at',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
