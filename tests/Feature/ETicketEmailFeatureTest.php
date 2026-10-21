<?php

namespace Tests\Feature;

use App\Mail\ETicketEmail;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Services\QrCodeRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event as EventFacade;
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
        $this->actingAs($customer)->post("/payments/{$order->payments()->first()->id}/check");

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

    public function test_eticket_email_has_a_plain_text_part_and_a_png_inline_image(): void
    {
        $sent = [];
        EventFacade::listen(MessageSent::class, function (MessageSent $event) use (&$sent): void {
            $sent[] = $event->message;
        });

        $order = $this->paidOrderWithTickets(1);

        $this->assertCount(1, $sent);

        $message = $sent[0];

        $this->assertNotNull($message->getTextBody(), 'E-tiket harus punya versi plain-text.');
        $this->assertStringContainsString($order->order_code, (string) $message->getTextBody());

        $raw = $message->toString();

        $this->assertStringContainsString('Content-Type: image/png', $raw);
        $this->assertStringNotContainsString('application/octet-stream', $raw);
    }

    public function test_day_specific_ticket_email_uses_the_selected_day_date(): void
    {
        Mail::fake();

        $customer = User::factory()->create();
        $event = Event::factory()->published()->create([
            'event_date' => '2026-10-07',
            'duration_type' => 'multi_day',
            'total_days' => 3,
        ]);
        $event->eventDays()->createMany([
            ['day_number' => 1, 'date' => '2026-10-07'],
            ['day_number' => 2, 'date' => '2026-10-08'],
            ['day_number' => 3, 'date' => '2026-10-09'],
        ]);
        $day = $event->eventDays()->where('day_number', 3)->firstOrFail();
        $category = $event->ticketCategories()->create([
            'name' => 'Reguler',
            'price' => 200_000,
            'total_quantity' => 10,
            'available_quantity' => 10,
            'max_per_order' => 5,
            'access_type' => 'SINGLE_DAY',
            'event_day_id' => $day->id,
        ]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'expires_at' => now()->addMinutes(15),
            'total_amount' => 200_000,
            'admin_fee' => 0,
            'grand_total' => 200_000,
        ]);
        $order->orderItems()->create([
            'ticket_category_id' => $category->id,
            'quantity' => 1,
            'price_per_unit' => 200_000,
            'subtotal' => 200_000,
        ]);
        $category->decrement('available_quantity');

        $this->actingAs($customer)->post("/orders/{$order->order_code}/pay", ['method' => 'qris']);
        $this->actingAs($customer)->post("/payments/{$order->payments()->firstOrFail()->id}/check");

        Mail::assertSent(
            ETicketEmail::class,
            fn (ETicketEmail $mail): bool => $mail->ticket['event_date'] === '09 Oct 2026'
                && $mail->ticket['ticket_day_label'] === 'Day 3',
        );
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
