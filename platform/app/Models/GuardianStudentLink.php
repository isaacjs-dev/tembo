<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GuardianStudentLink extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'guardian_id',
        'student_id',
        'created_by',
        'relationship',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function guardian()
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
