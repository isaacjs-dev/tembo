<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'target_audience',
        'price',
        'original_price',
        'promotional_price',
        'promo_starts_at',
        'promo_ends_at',
        'limits',
        'features',
        'is_visible',
        'is_most_popular',
        'sort_order',
        'status',
        'tier_level',
    ];

    protected $casts = [
        'limits' => 'array',
        'features' => 'array',
        'original_price' => 'decimal:2',
        'promotional_price' => 'decimal:2',
        'promo_starts_at' => 'datetime',
        'promo_ends_at' => 'datetime',
        'is_visible' => 'boolean',
        'is_most_popular' => 'boolean',
    ];

    /* ---- Relationships ---- */

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function planLimits()
    {
        return $this->hasMany(PlanLimit::class);
    }

    public function planFeatures()
    {
        return $this->hasMany(PlanFeature::class);
    }

    /* ---- Scopes ---- */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVisibleOnHome($query)
    {
        return $query->active()->where('is_visible', true)->orderBy('sort_order');
    }

    public function scopeForAudience($query, string $audience)
    {
        return $query->whereIn('target_audience', [$audience, 'both']);
    }

    /* ---- Helpers ---- */

    /**
     * Retorna o preço efetivo considerando promoção vigente.
     */
    public function getEffectivePriceAttribute(): string
    {
        if (
            $this->promotional_price !== null
            && $this->promo_starts_at?->isPast()
            && $this->promo_ends_at?->isFuture()
        ) {
            return $this->promotional_price;
        }

        return $this->original_price ?: $this->price;
    }

    /**
     * Retorna o limite para uma resource key.
     * Consulta plan_limits normalizado primeiro, depois fallback para JSON.
     * null = ilimitado.
     */
    public function getLimit(string $resourceKey): ?int
    {
        $normalized = $this->planLimits()->where('resource_key', $resourceKey)->first();

        if ($normalized) {
            return $normalized->limit_value; // null = ilimitado
        }

        // Fallback: JSON legado
        return $this->limits[$resourceKey] ?? null;
    }

    /**
     * Checa se o plano tem uma feature habilitada.
     */
    public function hasFeature(string $featureKey): bool
    {
        $normalized = $this->planFeatures()->where('feature_key', $featureKey)->first();

        if ($normalized) {
            return $normalized->enabled;
        }

        // Fallback: JSON legado
        $features = $this->features ?? [];
        if (is_array($features) && array_key_exists($featureKey, $features)) {
            return ! empty($features[$featureKey]);
        }

        return in_array($featureKey, $features);
    }
}
