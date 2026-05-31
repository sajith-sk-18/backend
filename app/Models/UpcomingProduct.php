<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UpcomingProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'brand', 'category_id', 'expected_at',
        'teaser', 'description', 'image_path', 'expected_price', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expected_at'    => 'date',
            'expected_price' => 'decimal:2',
            'is_active'      => 'boolean',
        ];
    }

    protected $appends = ['image_url'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
