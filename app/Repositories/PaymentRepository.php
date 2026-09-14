<?php

namespace App\Repositories;

use App\Models\Payment;

class PaymentRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Payment
    {
        return Payment::create($attributes);
    }

    public function findLocked(int $id): ?Payment
    {
        return Payment::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
    }
}
