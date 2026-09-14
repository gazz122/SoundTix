<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Performer;
use App\Models\TicketCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventDiscoveryFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_lists_published_events_only(): void
    {
        Event::factory()->published()->count(3)->create();
        Event::factory()->draft()->count(2)->create();
        Event::factory()->pending()->count(2)->create();

        $this->get('/')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Home/Index')
                    ->has('events.data', 3),
            );
    }

    public function test_homepage_search_filters_by_keyword(): void
    {
        Event::factory()->published()->create(['title' => 'Konser Noah Jakarta']);
        Event::factory()->published()->create(['title' => 'Festival Kopi Bandung']);

        $this->get('/?q=noah')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('events.data', 1)
                    ->where('events.data.0.title', 'Konser Noah Jakarta'),
            );
    }

    public function test_homepage_filters_by_city(): void
    {
        Event::factory()->published()->count(2)->create(['city' => 'Bandung']);
        Event::factory()->published()->create(['city' => 'Jakarta']);

        $this->get('/?city=Bandung')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('events.data', 2));
    }

    public function test_homepage_filters_by_date(): void
    {
        $targetDate = today()->addDays(10)->toDateString();

        Event::factory()->published()->create(['event_date' => $targetDate]);
        Event::factory()->published()->create(['event_date' => today()->addDays(20)->toDateString()]);

        $this->get('/?date='.$targetDate)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('events.data', 1));
    }

    public function test_homepage_filters_by_ticket_category(): void
    {
        $venueEvent = Event::factory()->published()->create();
        TicketCategory::factory()->for($venueEvent)->create(['name' => 'VIP']);
        TicketCategory::factory()->for($venueEvent)->create(['name' => 'Reguler']);

        $otherEvent = Event::factory()->published()->create();
        TicketCategory::factory()->for($otherEvent)->create(['name' => 'Festival']);

        $this->get('/?category=VIP')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('events.data', 1)
                    ->where('events.data.0.id', $venueEvent->id),
            );
    }

    public function test_homepage_shows_empty_state_when_no_results(): void
    {
        $this->get('/?q=tidak-ada-event')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('events.data', 0));
    }

    public function test_published_event_detail_renders(): void
    {
        $event = Event::factory()->published()->create();
        $event->performers()->detach();
        $event->performers()->attach(Performer::factory()->count(2)->create()->pluck('id'));
        TicketCategory::factory()->for($event)->count(3)->create();

        $this->get('/events/'.$event->slug)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Events/Show')
                    ->where('event.id', $event->id)
                    ->has('event.performers', 2)
                    ->has('event.ticket_categories', 3),
            );
    }

    public function test_unpublished_event_detail_is_not_accessible(): void
    {
        $draft = Event::factory()->draft()->create();

        $this->get('/events/'.$draft->slug)->assertNotFound();
    }

    public function test_api_lists_published_events_only(): void
    {
        Event::factory()->published()->count(2)->create();
        Event::factory()->draft()->create();
        Event::factory()->pending()->create();

        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'title',
                    'slug',
                    'starting_price',
                    'performers',
                ]],
            ]);
    }

    public function test_api_event_detail_returns_full_payload(): void
    {
        $event = Event::factory()->published()->create();
        TicketCategory::factory()->for($event)->create(['name' => 'VIP', 'price' => 250_000]);

        $this->getJson('/api/v1/events/'.$event->slug)
            ->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.ticket_categories.0.name', 'VIP')
            ->assertJsonPath('data.ticket_categories.0.price', 250_000);
    }

    public function test_api_hides_unpublished_event(): void
    {
        $draft = Event::factory()->draft()->create();

        $this->getJson('/api/v1/events/'.$draft->slug)->assertNotFound();
    }
}
