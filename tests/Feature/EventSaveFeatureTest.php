<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventSaveFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create();
    }

    public function test_guest_is_redirected_when_opening_explore(): void
    {
        $this->get('/jelajah')->assertRedirect('/login');
    }

    public function test_explore_page_lists_published_events_and_saved_flag(): void
    {
        $user = $this->customer();
        $saved = Event::factory()->published()->create(['event_date' => now()->addDays(30)->toDateString()]);
        $unsaved = Event::factory()->published()->create(['event_date' => now()->addDays(3)->toDateString()]);
        $user->savedEvents()->attach($saved->id);

        $this->actingAs($user)
            ->get('/jelajah')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Explore/Index')
                    ->has('events.data', 2)
                    ->where('events.data.0.is_saved', false)
                    ->where('events.data.1.is_saved', true),
            );
    }

    public function test_saving_event_persists_and_marks_as_saved(): void
    {
        $user = $this->customer();
        $event = Event::factory()->published()->create();

        $this->actingAs($user)
            ->post("/events/{$event->slug}/save")
            ->assertRedirect();

        $this->assertDatabaseHas('saved_events', [
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        $this->actingAs($user)
            ->get('/jelajah')
            ->assertInertia(fn (Assert $page) => $page->where('events.data.0.is_saved', true));
    }

    public function test_saving_same_event_twice_is_idempotent(): void
    {
        $user = $this->customer();
        $event = Event::factory()->published()->create();

        $this->actingAs($user)->post("/events/{$event->slug}/save");
        $this->actingAs($user)->post("/events/{$event->slug}/save");

        $this->assertSame(1, $user->savedEvents()->count());
    }

    public function test_unsaving_event_removes_it(): void
    {
        $user = $this->customer();
        $event = Event::factory()->published()->create();
        $user->savedEvents()->attach($event->id);

        $this->actingAs($user)
            ->delete("/events/{$event->slug}/save")
            ->assertRedirect();

        $this->assertDatabaseMissing('saved_events', [
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);
    }

    public function test_saved_page_lists_only_saved_events_newest_first(): void
    {
        $user = $this->customer();
        $older = Event::factory()->published()->create();
        $newer = Event::factory()->published()->create();

        $user->savedEvents()->attach($older->id, ['created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
        $user->savedEvents()->attach($newer->id, ['created_at' => now(), 'updated_at' => now()]);

        Event::factory()->published()->create();

        $this->actingAs($user)
            ->get('/saved')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('SavedEvents/Index')
                    ->has('events.data', 2)
                    ->where('events.data.0.id', $newer->id)
                    ->where('events.data.1.id', $older->id),
            );
    }

    public function test_saved_page_does_not_list_unpublished_events(): void
    {
        $user = $this->customer();
        $draft = Event::factory()->draft()->create(['event_date' => now()->addDays(10)->toDateString()]);
        $user->savedEvents()->attach($draft->id);

        $this->actingAs($user)
            ->get('/saved')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('events.data', 0));
    }
}
