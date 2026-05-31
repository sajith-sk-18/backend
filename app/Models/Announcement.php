<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'subtitle', 'description',
        'badge_label', 'theme',
        'cta_label', 'cta_url',
        'starts_at', 'ends_at', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'  => 'date',
            'ends_at'    => 'date',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected $appends = ['is_live'];

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
}
