<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description',
        'product_id', 'type', 'discount_value',
        'bundle_product_id', 'bundle_discount',
        'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value'  => 'decimal:2',
            'bundle_discount' => 'decimal:2',
            'starts_at'       => 'date',
            'ends_at'         => 'date',
            'is_active'       => 'boolean',
        ];
    }

    protected $appends = ['is_live', 'discounted_price'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bundleProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bundle_product_id');
    }

    public function scopeLive(Builder $q): Builder
    {
        $today = now()->toDateString();
        return $q->where('is_active', true)
            ->where(function ($w) use ($today) {
                $w->whereNull('starts_at')->orWhere('starts_at', '<=', $today);
            })
            ->where(function ($w) use ($today) {
                $w->whereNull('ends_at')->orWhere('ends_at', '>=', $today);
            });
    }

    public function getIsLiveAttribute(): bool
    {
        if (!$this->is_active) return false;
        $today = now()->startOfDay();
        if ($this->starts_at && $this->starts_at->gt($today)) return false;
        if ($this->ends_at   && $this->ends_at->lt($today))   return false;
        return true;
    }

    /**
     * For 'percent' / 'flat', returns the new price after discount.
     * For 'bundle' offers there is no main-product discount → returns null.
     */
    public function getDiscountedPriceAttribute(): ?float
    {
        if (!$this->relationLoaded('product') || !$this->product) return null;
        $price = (float) $this->product->price;

        if ($this->type === 'percent' && $this->discount_value !== null) {
            return round(max(0, $price * (1 - $this->discount_value / 100)), 2);
        }
        if ($this->type === 'flat' && $this->discount_value !== null) {
            return round(max(0, $price - (float) $this->discount_value), 2);
        }
        return null;
    }
}
