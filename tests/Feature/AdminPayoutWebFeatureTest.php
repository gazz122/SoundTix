<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payout;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPayoutWebFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_complete_payout_with_transfer_proof(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        [$organizer, $event] = $this->completedEventWithRevenue();
        $payout = Payout::factory()->create([
            'event_id' => $event->id,
            'organizer_id' => $organizer->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post("/admin/payouts/{$payout->id}/complete", [
                'transfer_proof' => UploadedFile::fake()->image('proof.jpg'),
                'admin_notes' => 'Ditransfer via BCA.',
            ])
            ->assertRedirect(route('admin.payouts.index'))
            ->assertSessionHas('success');

        $stored = Payout::find($payout->id);
        $this->assertSame('completed', $stored->status->value);
        $this->assertNotNull($stored->transfer_proof);
        $this->assertNotNull($stored->processed_at);
        $this->assertSame($admin->id, $stored->processed_by);
        $this->assertSame('Ditransfer via BCA.', $stored->admin_notes);
        Storage::disk('public')->assertExists($stored->transfer_proof);
    }

    public function test_transfer_proof_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        [$organizer, $event] = $this->completedEventWithRevenue();
        $payout = Payout::factory()->create([
            'event_id' => $event->id,
            'organizer_id' => $organizer->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post("/admin/payouts/{$payout->id}/complete", ['admin_notes' => 'Tanpa bukti.'])
            ->assertSessionHasErrors('transfer_proof');

        $this->assertDatabaseHas('payouts', ['id' => $payout->id, 'status' => 'pending']);
    }

    public function test_admin_cannot_complete_non_pending_payout(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        [$organizer, $event] = $this->completedEventWithRevenue();
        $payout = Payout::factory()->completed()->create([
            'event_id' => $event->id,
            'organizer_id' => $organizer->id,
        ]);

        $this->actingAs($admin)
            ->post("/admin/payouts/{$payout->id}/complete", [
                'transfer_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertSessionHasErrors('payout');
    }

    public function test_admin_can_view_payout_page_with_pending_payouts(): void
    {
        $admin = User::factory()->admin()->create();
        [$organizer, $event] = $this->completedEventWithRevenue();
        $payout = Payout::factory()->create([
            'event_id' => $event->id,
            'organizer_id' => $organizer->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->get('/admin/payouts')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Payouts/Index')
                ->has('payouts', 1)
                ->where('payouts.0.id', $payout->id)
                ->where('payouts.0.status', 'pending'));
    }

    public function test_organizer_cannot_access_admin_payout_page(): void
    {
        $this->actingAs(User::factory()->organizer()->create())
            ->get('/admin/payouts')
            ->assertForbidden();
    }

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
}
