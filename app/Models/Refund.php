<?php

namespace App\Models;

use App\Enums\RefundGateway;
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
    'refund_gateway',
    'gateway_reference_id',
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
            'refund_gateway' => RefundGateway::class,
            'gateway_response' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * The money is returned by hand, so the only thing standing between a refund
     * and a duplicate transfer is whether it has already been settled. A refund
     * that is mid-flight at the gateway must not be completed by hand either,
     * otherwise the buyer can be paid twice.
     */
    public function canBeCompleted(): bool
    {
        return ! in_array($this->status, [RefundStatus::Completed, RefundStatus::Processing], true);
    }

    /**
     * Whether the buyer still has to tell us where to send the money.
     */
    public function requiresBankDetails(): bool
    {
        return $this->status === RefundStatus::WaitingBankDetails;
    }

    public function hasBankDetails(): bool
    {
        return filled($this->bank_name) && filled($this->account_number) && filled($this->account_holder_name);
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
