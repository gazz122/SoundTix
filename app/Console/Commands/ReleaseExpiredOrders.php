<?php

namespace App\Console\Commands;

use App\Services\OrderExpirationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:release-expired')]
#[Description('Hapus holding lock order pending yang melewati batas waktu dan kembalikan stok tiket')]
class ReleaseExpiredOrders extends Command
{
    public function __construct(private OrderExpirationService $expiration)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $released = $this->expiration->releaseExpired();

        if ($released === 0) {
            $this->components->info('Tidak ada pesanan kadaluarsa yang perlu dirilis.');
        } else {
            $this->components->info("Dirilis {$released} pesanan kadaluarsa.");
        }

        return self::SUCCESS;
    }
}
