<?php

namespace App\Console\Commands;

use App\Services\EventCompletionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:complete-past')]
#[Description('Tandai event published yang sudah lewat tanggalnya sebagai completed agar payout dapat diajukan')]
class CompletePastEvents extends Command
{
    public function __construct(private EventCompletionService $events)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $completed = $this->events->completePastEvents();

        if ($completed === 0) {
            $this->components->info('Tidak ada event yang perlu diselesaikan.');
        } else {
            $this->components->info("{$completed} event ditandai selesai.");
        }

        return self::SUCCESS;
    }
}
