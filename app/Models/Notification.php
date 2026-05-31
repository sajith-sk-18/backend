<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'type', 'user_id', 'title', 'body', 'link', 'entity_type', 'entity_id', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function markRead(): void
    {
        if (!$this->read_at) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    /**
     * Notify a specific customer (their own notification feed).
     * `user_id` differentiates customer-scoped rows from admin-scoped rows
     * (which keep user_id NULL).
     */
    public static function forCustomer(int $userId, array $data): self
    {
        return self::create(array_merge($data, ['user_id' => $userId]));
    }

    /**
     * Mark every unread notification that points at this entity as read.
     * Optionally filter by notification type (e.g. only 'low_stock').
     *
     * Use case: when admin approves a review or resolves an enquiry, the
     * notification that announced it should no longer be unread.
     *
     * @return int Number of rows affected.
     */
    public static function markEntityRead(string $entityType, int $entityId, ?string $type = null): int
    {
        $q = self::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereNull('read_at');
        if ($type) $q->where('type', $type);

        return $q->update(['read_at' => now()]);
    }

    /**
     * Low-stock threshold. Anything at or below this value (and > 0) is "low".
     * 0 → out of stock, handled with a different copy.
     */
    public const LOW_STOCK_THRESHOLD = 5;

    /**
     * Emit (or skip via dedup) a low-stock notification for a product.
     * Dedup: don't create another low_stock notification for the same product
     * if one was emitted in the last 24 hours.
     *
     * @return self|null Returns the created notification, or null if skipped.
     */
    public static function lowStockFor(Product $product): ?self
    {
        $stock = (int) $product->stock;
        if ($stock > self::LOW_STOCK_THRESHOLD) return null;

        // Dedup: only suppress if an UNREAD low_stock notification already exists
        // for this product. Once admin sees it (and presumably restocks), a fresh
        // drop should produce a new alert.
        $recent = self::where('type', 'low_stock')
            ->where('entity_type', Product::class)
            ->where('entity_id', $product->id)
            ->whereNull('read_at')
            ->exists();
        if ($recent) return null;

        $isOut = $stock === 0;
        return self::create([
            'type'        => 'low_stock',
            'title'       => $isOut
                ? "Out of stock: {$product->name}"
                : "Low stock: {$product->name} ({$stock} left)",
            'body'        => $isOut
                ? 'This product is sold out. Restock soon or hide it from the storefront.'
                : "{$product->brand} · only {$stock} unit(s) remaining (threshold is " . self::LOW_STOCK_THRESHOLD . ').',
            'link'        => '/products',
            'entity_type' => Product::class,
            'entity_id'   => $product->id,
        ]);
    }
}
