<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionRolePermission extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'institution_role_id',
        'permission',
    ];

    public function role()
    {
        return $this->belongsTo(InstitutionRole::class, 'institution_role_id');
    }
}
