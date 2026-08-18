<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'path', 'is_primary', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        if (!$this->path) {
            return '';
        }
        // Absolute URLs (e.g. seeded stock photos / CDN) are served as-is; stored uploads
        // resolve via the public disk.
        if (str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://')) {
            return $this->path;
        }
        return Storage::disk('public')->url($this->path);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
