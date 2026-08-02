<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningMaterialProgress extends Model
{
    use HasFactory;

    protected $table = 'learning_material_progress';

    protected $fillable = [
        'organization_id',
        'learning_material_id',
        'student_id',
        'status',
        'view_count',
        'opened_at',
        'last_viewed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'view_count' => 'integer',
            'opened_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function material()
    {
        return $this->belongsTo(LearningMaterial::class, 'learning_material_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
