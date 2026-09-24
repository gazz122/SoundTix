<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\User;
use App\Repositories\OrderRepository;
use App\Repositories\TicketRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityConcurrencyFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_cannot_escalate_to_admin(): void
    {
        $this->post('/register', [
            'name' => 'Unauthorized Admin',
            'email' => 'not-admin@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'admin',
        ])->assertRedirect('/verify');

        $this->assertDatabaseMissing('users', [
            'email' => 'not-admin@example.com',
            'role' => 'admin',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'not-admin@example.com',
            'role' => 'customer',
        ]);
    }

    public function test_ticket_consumption_is_atomic_for_repeated_validation(): void
    {
        $ticket = Ticket::create([
            'order_item_id' => OrderItem::factory()->create()->id,
            'ticket_code' => 'STXTESTCODE01',
            'qr_payload' => 'test.payload',
            'status' => 'active',
        ]);
        $repository = app(TicketRepository::class);

        $this->assertSame(1, $repository->markUsedGuarded($ticket->id, User::factory()->gatekeeper()->create()->id));
        $this->assertSame(0, $repository->markUsedGuarded($ticket->id, User::factory()->gatekeeper()->create()->id));
        $this->assertSame('used', $ticket->fresh()->status);
    }

    public function test_order_expiration_guard_is_atomic_for_repeated_workers(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Pending->value,
            'expires_at' => now()->subMinute(),
        ]);
        $repository = app(OrderRepository::class);

        $this->assertSame(1, $repository->markExpiredGuarded($order->id));
        $this->assertSame(0, $repository->markExpiredGuarded($order->id));
        $this->assertSame(OrderStatus::Expired->value, $order->fresh()->status);
    }

    public function test_organizer_cannot_access_another_organizers_ticket_categories(): void
    {
        $owner = User::factory()->organizer()->create();
        $other = User::factory()->organizer()->create();
        $event = Event::factory()->draft()->create(['organizer_id' => $owner->id]);
        $category = $event->ticketCategories()->create([
            'name' => 'Reguler',
            'price' => 100_000,
            'total_quantity' => 10,
            'available_quantity' => 10,
            'max_per_order' => 5,
        ]);

        $this->actingAs($other)
            ->get("/organizer/events/{$event->id}/categories")
            ->assertNotFound();

        $this->actingAs($other)
            ->put("/organizer/events/{$event->id}/categories/{$category->id}", [
                'name' => 'Diubah',
                'price' => 100_000,
                'total_quantity' => 10,
                'max_per_order' => 5,
            ])
            ->assertNotFound();
    }
}
