<?php

namespace App\Repositories;

use App\Models\Performer;

class PerformerRepository
{
    public function firstOrCreateByName(string $name): Performer
    {
        return Performer::firstOrCreate(
            ['name' => $name],
            ['genre' => 'Umum'],
        );
    }
}
