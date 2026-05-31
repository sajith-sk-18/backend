<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'brand', 'slug', 'price', 'stock',
        'description', 'specs', 'is_featured', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'specs'       => 'array',
            'price'       => 'decimal:2',
            'is_featured' => 'boolean',
            'is_active'   => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $p) {
            if (empty($p->slug)) {
                $p->slug = Str::slug($p->name . '-' . substr(uniqid(), -5));
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('is_approved', true);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function liveOffers(): HasMany
    {
        return $this->hasMany(Offer::class)->live();
    }
}
