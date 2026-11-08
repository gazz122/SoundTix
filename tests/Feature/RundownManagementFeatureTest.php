<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Performer;
use App\Models\User;
use App\Services\RundownPayload;
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

    private function paidOrderFor(Event $event, User $customer): Order
    {
        $category = $event->ticketCategories()->create([
            'name' => 'Reguler',
            'price' => 100_000,
            'total_quantity' => 10,
            'available_quantity' => 10,
            'max_per_order' => 5,
        ]);

        $order = Order::factory()->paid()->create(['customer_id' => $customer->id]);
        $order->orderItems()->create([
            'ticket_category_id' => $category->id,
            'quantity' => 1,
            'price_per_unit' => 100_000,
            'subtotal' => 100_000,
        ]);

        return $order;
    }

    private function rundownPayload(Event $event, Order $order): string
    {
        return app(RundownPayload::class)->payload($order, $event);
    }

    private function rundownScanUrl(Event $event, Order $order): string
    {
        return '/rundown/scan?payload='.urlencode($this->rundownPayload($event, $order));
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

    public function test_public_event_detail_keeps_gate_times_but_no_longer_shows_rundowns(): void
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
                    ->missing('event.rundowns'),
            );
    }

    public function test_buyer_opens_the_rundown_page_with_a_payload(): void
    {
        $organizer = $this->organizer();
        $event = $this->publishedEvent($organizer);
        $event->rundowns()->create(['start_time' => '19:00', 'end_time' => '20:00', 'title' => 'Opening DJ']);
        $order = $this->paidOrderFor($event, User::factory()->create());

        $this->get($this->rundownScanUrl($event, $order))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Rundown/Show')
                    ->where('event.title', $event->title)
                    ->where('event.rundowns.0.title', 'Opening DJ'),
            );
    }

    public function test_scan_page_renders_the_camera_scanner(): void
    {
        $this->get('/rundown/scan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Rundown/Scan'));
    }

    public function test_rundown_page_rejects_an_invalid_payload(): void
    {
        $event = $this->publishedEvent($this->organizer());
        $order = $this->paidOrderFor($event, User::factory()->create());

        $this->get("/rundown/scan?payload=rundown.{$order->id}.{$event->id}.not-the-real-token")
            ->assertRedirect(route('rundown.scan'))
            ->assertSessionHasErrors('payload');
    }

    public function test_rundown_page_rejects_an_unpaid_order(): void
    {
        $event = $this->publishedEvent($this->organizer());
        $customer = User::factory()->create();
        $order = $this->paidOrderFor($event, $customer);
        $order->update(['status' => 'pending']);

        $this->get($this->rundownScanUrl($event, $order))
            ->assertRedirect(route('rundown.scan'))
            ->assertSessionHasErrors('payload');
    }

    public function test_paid_order_exposes_a_rundown_payload_and_pending_order_does_not(): void
    {
        $event = $this->publishedEvent($this->organizer());
        $event->rundowns()->create(['start_time' => '19:00', 'end_time' => '20:00', 'title' => 'Opening DJ']);
        $customer = User::factory()->create();
        $order = $this->paidOrderFor($event, $customer);
        $payload = $this->rundownPayload($event, $order);
        $viewerUrl = app(RundownPayload::class)->viewerUrl($order, $event->load(['eventDays', 'rundowns.performer']));

        $this->assertStringStartsWith('rundown.', $payload);
        $this->assertStringNotContainsString('http', $payload);
        $this->assertStringStartsWith(url('/rundown/scan?payload='), $viewerUrl);

        $this->get($viewerUrl)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rundown/Show')
                ->where('event.title', $event->title)
                ->has('event.rundowns', 1)
                ->where('event.rundowns.0.title', 'Opening DJ'));

        $event->rundowns()->first()->update(['title' => 'Closing DJ']);

        $this->get($viewerUrl)
            ->assertInertia(fn (Assert $page) => $page->where('event.rundowns.0.title', 'Closing DJ'));

        $this->actingAs($customer)
            ->get("/orders/{$order->order_code}")
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('order.rundown_links', 1)
                    ->where('order.rundown_links.0.event_id', $event->id)
                    ->where('order.rundown_links.0.payload', $payload)
                    ->where('order.rundown_links.0.viewer_url', $viewerUrl),
            );

        $pending = Order::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($customer)
            ->get("/orders/{$pending->order_code}")
            ->assertInertia(fn (Assert $page) => $page->has('order.rundown_links', 0));
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

    public function test_organizer_can_assign_a_performer_to_a_rundown_session(): void
    {
        $organizer = $this->organizer();
        $event = $this->publishedEvent($organizer);
        $performer = $event->performers()->firstOrFail();

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/rundowns", [
                'performer_id' => $performer->id,
                'start_time' => '20:00',
                'end_time' => '21:00',
            ])
            ->assertRedirect("organizer/events/{$event->id}");

        $this->assertDatabaseHas('event_rundowns', [
            'event_id' => $event->id,
            'performer_id' => $performer->id,
            'title' => null,
            'start_time' => '20:00',
        ]);
    }

    public function test_organizer_rundown_page_exposes_the_lineup_and_schedule_details(): void
    {
        $organizer = $this->organizer();
        $event = $this->publishedEvent($organizer);
        $performer = $event->performers()->firstOrFail();
        $event->rundowns()->create(['performer_id' => $performer->id, 'start_time' => '20:00']);

        $this->actingAs($organizer)
            ->get("/organizer/events/{$event->id}")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Organizer/Events/Show')
                    ->has('event.lineup')
                    ->where('event.rundowns.0.performer_name', $performer->name),
            );
    }

    public function test_organizer_cannot_assign_a_performer_outside_the_event_lineup(): void
    {
        $organizer = $this->organizer();
        $event = $this->publishedEvent($organizer);
        $foreignPerformer = Performer::factory()->create();

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/rundowns", [
                'performer_id' => $foreignPerformer->id,
                'start_time' => '20:00',
            ])
            ->assertSessionHasErrors('performer_id');

        $this->assertDatabaseCount('event_rundowns', 0);
    }

    public function test_rundown_requires_a_title_or_a_performer(): void
    {
        $organizer = $this->organizer();
        $event = $this->publishedEvent($organizer);

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/rundowns", ['start_time' => '20:00'])
            ->assertSessionHasErrors(['title', 'performer_id']);
    }

    public function test_organizer_can_reschedule_a_performer_and_buyers_see_the_new_time(): void
    {
        $organizer = $this->organizer();
        $event = $this->publishedEvent($organizer);
        $performer = $event->performers()->firstOrFail();
        $rundown = $event->rundowns()->create([
            'performer_id' => $performer->id,
            'start_time' => '20:00',
            'end_time' => '21:00',
        ]);

        $this->actingAs($organizer)
            ->put("/organizer/events/{$event->id}/rundowns/{$rundown->id}", [
                'performer_id' => $performer->id,
                'start_time' => '20:30',
                'end_time' => '21:30',
            ])
            ->assertRedirect("organizer/events/{$event->id}");

        $order = $this->paidOrderFor($event, User::factory()->create());

        $this->get($this->rundownScanUrl($event, $order))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('event.rundowns.0.start_time', '20:30')
                    ->where('event.rundowns.0.end_time', '21:30')
                    ->where('event.rundowns.0.performer.name', $performer->name),
            );
    }

    public function test_rundowns_are_listed_in_start_time_order(): void
    {
        $organizer = $this->organizer();
        $event = $this->publishedEvent($organizer);

        foreach (['21:00' => 'Closing', '19:00' => 'Opening', '20:00' => 'Main'] as $time => $title) {
            $event->rundowns()->create(['start_time' => $time, 'title' => $title]);
        }

        $order = $this->paidOrderFor($event, User::factory()->create());

        $this->get($this->rundownScanUrl($event, $order))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('event.rundowns.0.title', 'Opening')
                    ->where('event.rundowns.1.title', 'Main')
                    ->where('event.rundowns.2.title', 'Closing'),
            );
    }

    public function test_multi_day_rundowns_require_a_day_and_keep_their_day_reference(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'duration_type' => 'multi_day',
            'total_days' => 2,
        ]);
        $event->eventDays()->create(['day_number' => 1, 'date' => '2026-12-01']);
        $dayTwo = $event->eventDays()->create(['day_number' => 2, 'date' => '2026-12-02']);

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/rundowns", [
                'start_time' => '19:00',
                'title' => 'Opening',
            ])
            ->assertSessionHasErrors('event_day_id');

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/rundowns", [
                'event_day_id' => $dayTwo->id,
                'start_time' => '19:00',
                'title' => 'Opening',
            ])
            ->assertRedirect("organizer/events/{$event->id}");

        $this->assertDatabaseHas('event_rundowns', [
            'event_id' => $event->id,
            'event_day_id' => $dayTwo->id,
        ]);
    }

    public function test_organizer_can_reorder_sessions_and_each_duration_is_preserved(): void
    {
        $organizer = $this->organizer();
        $event = $this->publishedEvent($organizer);

        $opening = $event->rundowns()->create(['start_time' => '19:00', 'end_time' => '19:30', 'title' => 'Opening']);
        $main = $event->rundowns()->create(['start_time' => '19:30', 'end_time' => '20:30', 'title' => 'Main']);
        $closing = $event->rundowns()->create(['start_time' => '20:30', 'end_time' => '21:00', 'title' => 'Closing']);

        $this->actingAs($organizer)
            ->put("/organizer/events/{$event->id}/rundowns/reorder", [
                'ids' => [$closing->id, $opening->id, $main->id],
            ])
            ->assertRedirect("organizer/events/{$event->id}");

        $this->assertDatabaseHas('event_rundowns', ['id' => $closing->id, 'start_time' => '19:00', 'end_time' => '19:30']);
        $this->assertDatabaseHas('event_rundowns', ['id' => $opening->id, 'start_time' => '19:30', 'end_time' => '20:00']);
        $this->assertDatabaseHas('event_rundowns', ['id' => $main->id, 'start_time' => '20:00', 'end_time' => '21:00']);

        $order = $this->paidOrderFor($event, User::factory()->create());

        $this->get($this->rundownScanUrl($event, $order))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('event.rundowns.0.title', 'Closing')
                    ->where('event.rundowns.1.title', 'Opening')
                    ->where('event.rundowns.2.title', 'Main'),
            );
    }

    public function test_reordering_uses_a_thirty_minute_duration_when_end_time_is_missing(): void
    {
        $organizer = $this->organizer();
        $event = $this->publishedEvent($organizer);

        $first = $event->rundowns()->create(['start_time' => '19:00', 'end_time' => '19:30', 'title' => 'Opening']);
        $withoutEnd = $event->rundowns()->create(['start_time' => '19:30', 'title' => 'Tanpa jam selesai']);
        $last = $event->rundowns()->create(['start_time' => '20:00', 'end_time' => '20:30', 'title' => 'Closing']);

        $this->actingAs($organizer)
            ->put("/organizer/events/{$event->id}/rundowns/reorder", [
                'ids' => [$withoutEnd->id, $first->id, $last->id],
            ])
            ->assertRedirect("organizer/events/{$event->id}");

        $this->assertDatabaseHas('event_rundowns', ['id' => $withoutEnd->id, 'start_time' => '19:00', 'end_time' => '19:30']);
        $this->assertDatabaseHas('event_rundowns', ['id' => $first->id, 'start_time' => '19:30', 'end_time' => '20:00']);
        $this->assertDatabaseHas('event_rundowns', ['id' => $last->id, 'start_time' => '20:00', 'end_time' => '20:30']);
    }

    public function test_reordering_rejects_sessions_from_another_event(): void
    {
        $organizer = $this->organizer();
        $event = $this->publishedEvent($organizer);
        $foreign = $this->publishedEvent($this->organizer());
        $foreignRundown = $foreign->rundowns()->create(['start_time' => '19:00', 'title' => 'Bukan milik event ini']);

        $this->actingAs($organizer)
            ->put("/organizer/events/{$event->id}/rundowns/reorder", ['ids' => [$foreignRundown->id]])
            ->assertNotFound();
    }

    public function test_reordering_rejects_sessions_from_different_days(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'duration_type' => 'multi_day',
            'total_days' => 2,
        ]);
        $dayOne = $event->eventDays()->create(['day_number' => 1, 'date' => '2026-12-01']);
        $dayTwo = $event->eventDays()->create(['day_number' => 2, 'date' => '2026-12-02']);

        $first = $event->rundowns()->create(['event_day_id' => $dayOne->id, 'start_time' => '19:00', 'title' => 'Day one']);
        $second = $event->rundowns()->create(['event_day_id' => $dayTwo->id, 'start_time' => '19:00', 'title' => 'Day two']);

        $this->actingAs($organizer)
            ->put("/organizer/events/{$event->id}/rundowns/reorder", ['ids' => [$first->id, $second->id]])
            ->assertStatus(422);
    }
}
