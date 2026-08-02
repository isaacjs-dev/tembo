<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OmrCalibration extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'offset_x',
        'offset_y',
        'scale_x',
        'scale_y',
        'rotation_deg',
    ];

    protected $casts = [
        'offset_x' => 'float',
        'offset_y' => 'float',
        'scale_x' => 'float',
        'scale_y' => 'float',
        'rotation_deg' => 'float',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
