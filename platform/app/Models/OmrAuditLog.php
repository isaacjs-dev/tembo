<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OmrAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'omr_scan_id',
        'user_id',
        'action',
        'previous_data',
        'new_data',
    ];

    protected $casts = [
        'previous_data' => 'array',
        'new_data' => 'array',
    ];

    public function scan()
    {
        return $this->belongsTo(OmrScan::class, 'omr_scan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
