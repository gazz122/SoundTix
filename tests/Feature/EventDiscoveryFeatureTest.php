<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Event;
use App\Models\Performer;
use App\Models\TicketCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_homepage_search_matches_performer_name(): void
    {
        $noah = Event::factory()->published()->create(['title' => 'Konser Akbar 2026']);
        $noah->performers()->sync(Performer::factory()->create(['name' => 'Dewa 19']));

        Event::factory()->published()->create();

        $this->get('/?q=dewa')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('events.data', 1)
                    ->where('events.data.0.id', $noah->id),
            );
    }

    public function test_homepage_search_matches_location(): void
    {
        $event = Event::factory()->published()->create(['city' => 'Bandung', 'title' => 'Festival Kopi']);

        $this->get('/?q=bandung')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('events.data', 1)
                    ->where('events.data.0.id', $event->id),
            );
    }

    public function test_homepage_exposes_active_banners_ordered_for_carousel(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('banners/second.png', 'x');
        Storage::disk('public')->put('banners/first.png', 'x');
        Banner::factory()->create(['title' => 'Promo-library', 'image_path' => 'banners/library.png', 'order' => 20]);
        Banner::factory()->create(['title' => 'Promo-first', 'image_path' => 'banners/first.png', 'order' => 1]);
        Banner::factory()->create(['title' => 'Promo-second', 'image_path' => 'banners/second.png', 'order' => 10]);
        Banner::factory()->inactive()->create(['image_path' => 'banners/hidden.png']);

        $this->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->has('banners', 3)
                ->where('banners.0.title', 'Promo-first')
                ->where('banners.0.image_url', Storage::disk('public')->url('banners/first.png'))
                ->where('banners.1.title', 'Promo-second')
                ->where('banners.2.title', 'Promo-library'),
        );
    }

    public function test_homepage_falls_back_to_static_banners_when_none_active(): void
    {
        Banner::factory()->inactive()->create();

        $this->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->has('banners', 3)
                ->where('banners.0.image_url', '/images/banners/tiket-digital.svg'),
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

    public function test_published_event_detail_renders_when_close_gate_is_missing(): void
    {
        $event = Event::factory()->published()->create([
            'open_gate' => '2026-11-02 05:23:00',
            'close_gate' => null,
        ]);

        $this->get('/events/'.$event->slug)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Events/Show')
                    ->where('event.open_gate', '2026-11-02 12:23')
                    ->where('event.close_gate', null),
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
