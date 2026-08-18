<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enquiry extends Model
{
    use HasFactory;

    /** Allowed status values. */
    public const STATUSES = ['new', 'in_progress', 'responded', 'closed'];

    protected $fillable = [
        'customer_id', 'product_id',
        'name', 'email', 'phone', 'whatsapp',
        'message', 'admin_reply',
        'status', 'resolved_at', 'replied_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'replied_at'  => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
