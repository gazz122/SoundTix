<?php

namespace Tests\Feature;

use App\Mail\ETicketEmail;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Services\QrCodeRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ETicketEmailFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrderWithTickets(int $quantity): Order
    {
        $customer = User::factory()->create();
        $event = Event::factory()->published()->create();
        $category = $event->ticketCategories()->create([
            'name' => 'Reguler',
            'price' => 200_000,
            'total_quantity' => 100,
            'available_quantity' => 100,
            'max_per_order' => 5,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'expires_at' => now()->addMinutes(15),
            'total_amount' => 200_000 * $quantity,
            'admin_fee' => 0,
            'grand_total' => 200_000 * $quantity,
        ]);
        $order->orderItems()->create([
            'ticket_category_id' => $category->id,
            'quantity' => $quantity,
            'price_per_unit' => 200_000,
            'subtotal' => 200_000 * $quantity,
        ]);
        $category->decrement('available_quantity', $quantity);

        $this->actingAs($customer)->post("/orders/{$order->order_code}/pay", ['method' => 'qris']);
        $this->actingAs($customer)->post("/payments/{$order->payments()->first()->id}/settle");

        return $order->fresh();
    }

    public function test_one_eticket_email_is_sent_per_ticket_after_settlement(): void
    {
        Mail::fake();

        $order = $this->paidOrderWithTickets(3);
        $ticketCodes = Ticket::pluck('ticket_code')->sort()->values();
        $eventTitle = $order->orderItems->first()->ticketCategory->event->title;

        Mail::assertSent(ETicketEmail::class, 3);

        Mail::assertSent(ETicketEmail::class, function (ETicketEmail $mail) use ($order, $ticketCodes, $eventTitle) {
            return in_array($mail->ticket['ticket_code'], $ticketCodes->all(), true)
                && $mail->order['order_code'] === $order->order_code
                && $mail->ticket['event_title'] === $eventTitle
                && str_starts_with($mail->qrPng, "\x89PNG");
        });

        foreach (Mail::sent(ETicketEmail::class) as $mail) {
            $this->assertTrue(getimagesizefromstring($mail->qrPng)[2] === IMAGETYPE_PNG);
        }
    }

    public function test_qr_code_renderer_produces_a_valid_png(): void
    {
        $png = (new QrCodeRenderer)->png('SOUNDTIX.1.10.signature');

        $this->assertStringStartsWith("\x89PNG", $png);
        $image = getimagesizefromstring($png);
        $this->assertNotFalse($image);
        $this->assertSame('image/png', $image['mime']);
        $this->assertSame(320, $image[0]);
    }
}
