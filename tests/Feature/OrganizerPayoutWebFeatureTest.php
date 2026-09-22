<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerPayoutWebFeatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Event}
     */
    private function completedEventWithRevenue(float $subtotal = 1_000_000): array
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->completed()->create(['organizer_id' => $organizer->id]);
        $category = TicketCategory::factory()->create(['event_id' => $event->id, 'price' => $subtotal]);

        $order = Order::factory()->paid()->create([
            'total_amount' => $subtotal,
            'grand_total' => $subtotal,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'ticket_category_id' => $category->id,
            'quantity' => 1,
            'price_per_unit' => $subtotal,
            'subtotal' => $subtotal,
        ]);

        return [$organizer, $event];
    }

    private function bankPayload(): array
    {
        return [
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'PT Soundtix Promoter',
        ];
    }

    public function test_organizer_can_view_payout_page_with_eligible_events(): void
    {
        [$organizer, $event] = $this->completedEventWithRevenue();

        $this->actingAs($organizer)->get('/organizer/payouts')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Organizer/Payouts/Index')
                ->has('events', 1)
                ->where('events.0.id', $event->id)
                ->where('events.0.eligible', true)
                ->where('events.0.net_amount', 950_000));
    }

    public function test_organizer_can_withdraw_via_web_form(): void
    {
        [$organizer, $event] = $this->completedEventWithRevenue();

        $this->actingAs($organizer)
            ->post("/organizer/payouts/{$event->id}/withdraw", $this->bankPayload())
            ->assertRedirect(route('organizer.payouts.index'));

        $this->assertDatabaseHas('payouts', [
            'event_id' => $event->id,
            'organizer_id' => $organizer->id,
            'status' => 'pending',
            'gross_amount' => 1_000_000,
            'net_amount' => 950_000,
        ]);
    }

    public function test_organizer_cannot_withdraw_an_event_that_is_not_eligible(): void
    {
        $organizer = User::factory()->organizer()->create();
        $future = Event::factory()->published()->create([
            'organizer_id' => $organizer->id,
            'event_date' => now()->addWeek()->toDateString(),
        ]);

        $this->actingAs($organizer)
            ->post("/organizer/payouts/{$future->id}/withdraw", $this->bankPayload())
            ->assertSessionHasErrors('event');

        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_organizer_cannot_withdraw_another_organizer_event(): void
    {
        [, $event] = $this->completedEventWithRevenue();
        $intruder = User::factory()->organizer()->create();

        $this->actingAs($intruder)
            ->post("/organizer/payouts/{$event->id}/withdraw", $this->bankPayload())
            ->assertSessionHasErrors('event');

        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_guest_cannot_access_payout_page(): void
    {
        $this->get('/organizer/payouts')->assertRedirect(route('login'));
    }
}
