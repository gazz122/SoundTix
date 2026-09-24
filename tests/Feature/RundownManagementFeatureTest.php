<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RundownManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function organizer(): User
    {
        return User::factory()->organizer()->create();
    }

    private function publishedEvent(User $organizer): Event
    {
        return Event::factory()->published()->create(['organizer_id' => $organizer->id]);
    }

    public function test_organizer_can_add_a_rundown_session(): void
    {
        $organizer = $this->organizer();
        $event = $this->publishedEvent($organizer);

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/rundowns", [
                'start_time' => '19:00',
                'end_time' => '20:00',
                'title' => 'Opening DJ',
                'description' => 'Sesi pembuka',
            ])
            ->assertRedirect("organizer/events/{$event->id}");

        $this->assertDatabaseHas('event_rundowns', [
            'event_id' => $event->id,
            'title' => 'Opening DJ',
            'start_time' => '19:00',
            'end_time' => '20:00',
            'description' => 'Sesi pembuka',
        ]);
    }

    public function test_organizer_can_delete_a_rundown_session(): void
    {
        $organizer = $this->organizer();
        $event = $this->publishedEvent($organizer);
        $rundown = $event->rundowns()->create([
            'start_time' => '21:00',
            'title' => 'Band Utama',
        ]);

        $this->actingAs($organizer)
            ->delete("/organizer/events/{$event->id}/rundowns/{$rundown->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('event_rundowns', ['id' => $rundown->id]);
    }

    public function test_other_organizer_cannot_manage_rundowns(): void
    {
        $event = $this->publishedEvent($this->organizer());

        $this->actingAs($this->organizer())
            ->post("/organizer/events/{$event->id}/rundowns", [
                'start_time' => '19:00',
                'title' => 'Hack',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('event_rundowns', 0);
    }

    public function test_public_event_detail_includes_rundowns_and_gate_times(): void
    {
        $organizer = $this->organizer();
        $openWib = '2026-12-01T18:00';
        $closeWib = '2026-12-01T23:00';
        $event = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'event_date' => '2025-12-31',
            'open_gate' => Carbon::parse($openWib, 'Asia/Jakarta')->setTimezone('UTC'),
            'close_gate' => Carbon::parse($closeWib, 'Asia/Jakarta')->setTimezone('UTC'),
        ]);
        $event->rundowns()->create([
            'start_time' => '19:00',
            'end_time' => '20:00',
            'title' => 'Opening DJ',
            'description' => 'Sesi pembuka',
        ]);

        $this->get("/events/{$event->slug}")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('event.open_gate', '2026-12-01 18:00')
                    ->where('event.close_gate', '2026-12-01 23:00')
                    ->has('event.rundowns', 1)
                    ->where('event.rundowns.0.title', 'Opening DJ'),
            );
    }

    public function test_gatekeeper_dashboard_shows_rundowns_of_upcoming_events(): void
    {
        $organizer = $this->organizer();
        $event = $this->publishedEvent($organizer);
        $event->rundowns()->create(['start_time' => '19:00', 'title' => 'Opening DJ']);

        $this->actingAs(User::factory()->gatekeeper()->create())
            ->get('/dashboard/scan')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('rundowns', 1)
                    ->where('rundowns.0.id', $event->id)
                    ->where('rundowns.0.rundowns.0.title', 'Opening DJ'),
            );
    }

    public function test_organizer_gate_input_is_stored_as_utc_and_round_trips_to_wib(): void
    {
        $openWib = '2026-12-01T18:00';
        $closeWib = '2026-12-01T23:00';

        $this->actingAs($this->organizer())
            ->post('/organizer/events', [
                'title' => 'Konser Band Terkenal',
                'description' => 'Konser akbar.',
                'venue_name' => 'Istora Senayan',
                'city' => 'Jakarta',
                'address' => 'GBK, Senayan',
                'event_date' => '2026-12-01',
                'start_time' => '19:00',
                'end_time' => '22:00',
                'open_gate' => $openWib,
                'close_gate' => $closeWib,
                'status' => 'draft',
            ])
            ->assertRedirect(route('organizer.events.index'));

        $event = Event::firstOrFail();

        $this->assertTrue($event->open_gate->equalTo(Carbon::parse($openWib, 'Asia/Jakarta')->setTimezone('UTC')));
        $this->assertTrue($event->close_gate->equalTo(Carbon::parse($closeWib, 'Asia/Jakarta')->setTimezone('UTC')));

        $event->update(['status' => 'published']);

        $this->get("/events/{$event->slug}")
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('event.open_gate', '2026-12-01 18:00')
                    ->where('event.close_gate', '2026-12-01 23:00'),
            );
    }
}
