<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'performer_id', 'event_day_id', 'start_time', 'end_time', 'title', 'description'])]
class EventRundown extends Model
{
    use HasFactory;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(Performer::class);
    }

    public function eventDay(): BelongsTo
    {
        return $this->belongsTo(EventDay::class);
    }
}
