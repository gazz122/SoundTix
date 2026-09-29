<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TicketCategoryManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function organizer(array $attributes = []): User
    {
        return User::factory()->organizer()->create($attributes);
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'VIP',
            'description' => 'Area VIP dengan akses khusus.',
            'price' => 500_000,
            'total_quantity' => 100,
            'available_quantity' => 100,
            'max_per_order' => 5,
            ...$overrides,
        ];
    }

    public function test_organizer_sees_categories_for_own_event(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        TicketCategory::factory()->for($event)->create([
            'name' => 'VIP',
            'price' => 500_000,
            'total_quantity' => 200,
            'available_quantity' => 200,
        ]);

        $this->actingAs($organizer)
            ->get("/organizer/events/{$event->id}/categories")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Organizer/Events/Categories')
                    ->has('categories', 1)
                    ->where('categories.0.name', 'VIP')
                    ->where('categories.0.sold', 0),
            );
    }

    public function test_organizer_can_create_category_with_default_stock(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/categories", $this->validPayload(['available_quantity' => null]))
            ->assertRedirectToRoute('organizer.events.categories', $event->id);

        $category = $event->ticketCategories()->firstOrFail();
        $this->assertSame('VIP', $category->name);
        $this->assertSame(100, $category->total_quantity);
        $this->assertSame(100, $category->available_quantity);
    }

    public function test_category_creation_validates_price_stock_and_max_per_order(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/categories", $this->validPayload([
                'price' => -1,
                'total_quantity' => 0,
                'max_per_order' => 6,
            ]))
            ->assertSessionHasErrors(['price', 'total_quantity', 'max_per_order']);

        $this->assertCount(0, $event->ticketCategories()->get());
    }

    public function test_organizer_can_update_category(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $category = TicketCategory::factory()->for($event)->create([
            'name' => 'Reguler',
            'price' => 150_000,
            'total_quantity' => 100,
            'available_quantity' => 100,
        ]);

        $this->actingAs($organizer)
            ->put("/organizer/events/{$event->id}/categories/{$category->id}", $this->validPayload([
                'name' => 'Reguler 2',
                'price' => 175_000,
                'total_quantity' => 250,
            ]))
            ->assertRedirect();

        $fresh = $category->fresh();
        $this->assertSame('Reguler 2', $fresh->name);
        $this->assertSame('175000.00', (string) $fresh->price);
        $this->assertSame(250, $fresh->total_quantity);
        $this->assertSame(250, $fresh->available_quantity);
    }

    public function test_available_stock_adjusts_with_total_keeping_sold_constant(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $category = TicketCategory::factory()->for($event)->create([
            'total_quantity' => 100,
            'available_quantity' => 70,
        ]);

        $this->actingAs($organizer)
            ->put("/organizer/events/{$event->id}/categories/{$category->id}", $this->validPayload([
                'total_quantity' => 150,
            ]))
            ->assertRedirect();

        $fresh = $category->fresh();
        $this->assertSame(150, $fresh->total_quantity);
        $this->assertSame(120, $fresh->available_quantity);
    }

    public function test_total_cannot_be_lowered_below_sold_quantity(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $category = TicketCategory::factory()->for($event)->create([
            'total_quantity' => 100,
            'available_quantity' => 70,
        ]);

        $this->actingAs($organizer)
            ->put("/organizer/events/{$event->id}/categories/{$category->id}", $this->validPayload([
                'total_quantity' => 20,
            ]))
            ->assertStatus(422);

        $this->assertSame(100, $category->fresh()->total_quantity);
    }

    public function test_soldout_category_cannot_be_deleted(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $category = TicketCategory::factory()->for($event)->soldOut()->create();

        $this->actingAs($organizer)
            ->delete("/organizer/events/{$event->id}/categories/{$category->id}")
            ->assertStatus(422);

        $this->assertNotNull($category->fresh());
    }

    public function test_organizer_can_delete_category_with_no_sales(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $category = TicketCategory::factory()->for($event)->create();

        $this->actingAs($organizer)
            ->delete("/organizer/events/{$event->id}/categories/{$category->id}")
            ->assertRedirect();

        $this->assertNull($category->fresh());
    }

    public function test_organizer_cannot_manage_other_organizers_event(): void
    {
        $organizer = $this->organizer();
        $other = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $other->id]);

        $this->actingAs($organizer)
            ->get("/organizer/events/{$event->id}/categories")
            ->assertNotFound();

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/categories", $this->validPayload())
            ->assertNotFound();
    }

    public function test_admin_can_manage_any_event_categories(): void
    {
        $admin = User::factory()->admin()->create();
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $this->actingAs($admin)
            ->get("/organizer/events/{$event->id}/categories")
            ->assertOk();
    }

    public function test_customer_cannot_access_categories_page(): void
    {
        $customer = User::factory()->create();
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $this->actingAs($customer)
            ->get("/organizer/events/{$event->id}/categories")
            ->assertForbidden();
    }

    public function test_guest_redirected_to_login(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $this->get("/organizer/events/{$event->id}/categories")->assertRedirect(route('login'));
    }

    public function test_category_defaults_to_all_days_without_event_day(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/categories", $this->validPayload())
            ->assertRedirect();

        $category = $event->ticketCategories()->firstOrFail();
        $this->assertSame('ALL_DAYS', $category->access_type);
        $this->assertNull($category->event_day_id);
    }

    public function test_organizer_can_create_single_day_category_tied_to_event_day(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $day = $event->eventDays()->create([
            'day_number' => 2,
            'date' => now()->addDays(6)->toDateString(),
        ]);

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/categories", $this->validPayload([
                'access_type' => 'SINGLE_DAY',
                'event_day_id' => $day->id,
            ]))
            ->assertRedirect();

        $category = $event->ticketCategories()->firstOrFail();
        $this->assertSame('SINGLE_DAY', $category->access_type);
        $this->assertSame($day->id, $category->event_day_id);
    }

    public function test_single_day_category_requires_event_day(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/categories", $this->validPayload(['access_type' => 'SINGLE_DAY']))
            ->assertStatus(422);

        $this->assertCount(0, $event->ticketCategories()->get());
    }

    public function test_single_day_category_rejects_another_events_day(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $other = Event::factory()->published()->create();
        $otherDay = $other->eventDays()->create([
            'day_number' => 1,
            'date' => now()->addDays(5)->toDateString(),
        ]);

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/categories", $this->validPayload([
                'access_type' => 'SINGLE_DAY',
                'event_day_id' => $otherDay->id,
            ]))
            ->assertStatus(422);

        $this->assertCount(0, $event->ticketCategories()->get());
    }

    public function test_categories_page_serializes_event_days_for_day_selection(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $event->eventDays()->create(['day_number' => 1, 'date' => now()->addDays(5)->toDateString()]);
        $event->eventDays()->create(['day_number' => 2, 'date' => now()->addDays(6)->toDateString()]);

        $this->actingAs($organizer)
            ->get("/organizer/events/{$event->id}/categories")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('event_days', 2)
                    ->where('event_days.0.day_number', 1)
                    ->where('event_days.1.day_number', 2),
            );
    }
}
