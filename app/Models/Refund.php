<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'payment_id',
    'amount',
    'bank_name',
    'account_number',
    'account_holder_name',
    'proof_of_transfer',
    'refund_key',
    'status',
    'failure_reason',
    'gateway_response',
    'processed_at',
    'reason',
    'notes',
])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => RefundStatus::class,
            'gateway_response' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * The money is returned by hand, so the only thing standing between a refund
     * and a duplicate transfer is whether it has already been settled.
     */
    public function canBeCompleted(): bool
    {
        return $this->status !== RefundStatus::Completed;
    }

    public function failureMessage(): string
    {
        return $this->failure_reason ?: 'Refund gagal diproses.';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
