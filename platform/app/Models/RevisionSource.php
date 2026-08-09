<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionSource extends Model
{
    protected $fillable = ['revision_id', 'source_type', 'source_id'];

    public function revision()
    {
        return $this->belongsTo(Revision::class);
    }

    public function source()
    {
        return $this->morphTo();
    }
}
