<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionImport extends Model
{
    protected $fillable = ['revision_id', 'imported_by', 'schema_version', 'payload', 'items_imported', 'status', 'validation_errors'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'validation_errors' => 'array'];
    }

    public function revision()
    {
        return $this->belongsTo(Revision::class);
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
