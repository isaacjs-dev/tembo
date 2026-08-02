<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScanMode extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_default',
        'requires_predownload',
        'requires_qr_data',
        'offline_capable',
        'qr_payload_schema',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'requires_predownload' => 'boolean',
            'requires_qr_data' => 'boolean',
            'offline_capable' => 'boolean',
            'qr_payload_schema' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /* ── Scopes ── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /* ── Helpers ── */

    /**
     * Exporta para o payload de configuração do Duoscanner.
     */
    public function toConfigPayload(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'requires_predownload' => $this->requires_predownload,
            'requires_qr_data' => $this->requires_qr_data,
            'offline_capable' => $this->offline_capable,
        ];
    }
}
