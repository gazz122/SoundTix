<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_code',
    'customer_id',
    'total_amount',
    'admin_fee',
    'grand_total',
    'status',
    'expires_at',
    'paid_at',
])]
class Order extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'admin_fee' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        // Orders are historical financial records: an order stays readable even
        // after its customer is soft deleted, otherwise every screen that shows
        // a customer name or notifies them fatals on a null relation.
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
