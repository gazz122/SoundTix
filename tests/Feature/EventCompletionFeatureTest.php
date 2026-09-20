<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCompletionFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_marks_past_published_events_completed(): void
    {
        $past = Event::factory()->published()->create(['event_date' => now()->subDay()->toDateString()]);
        $future = Event::factory()->published()->create(['event_date' => now()->addDay()->toDateString()]);
        $draft = Event::factory()->draft()->create(['event_date' => now()->subDays(2)->toDateString()]);

        $this->artisan('events:complete-past')->assertExitCode(0);

        $this->assertSame(EventStatus::Completed->value, $past->fresh()->status);
        $this->assertSame(EventStatus::Published->value, $future->fresh()->status);
        $this->assertSame(EventStatus::Draft->value, $draft->fresh()->status);
    }

    public function test_completed_event_becomes_eligible_for_payout(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'event_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('events:complete-past')->assertExitCode(0);

        $this->assertSame(EventStatus::Completed->value, $event->fresh()->status);
    }
}
