<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizerReportFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = User::factory()->organizer()->create();
    }

    private function seedPaidItem(): Order
    {
        $event = Event::factory()->create(['organizer_id' => $this->organizer->id]);
        $cat = TicketCategory::factory()->create(['event_id' => $event->id, 'price' => 150000]);
        $order = Order::factory()->create([
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'grand_total' => 300000,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'ticket_category_id' => $cat->id,
            'quantity' => 2,
            'price_per_unit' => 150000,
            'subtotal' => 300000,
        ]);

        return $order;
    }

    public function test_organizer_can_open_reports_page(): void
    {
        $this->seedPaidItem();

        $this->actingAs($this->organizer)
            ->get('/organizer/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organizer/Reports/Index')
                ->has('items', 1)
                ->where('summary.total_quantity', 2));
    }

    public function test_organizer_can_download_csv(): void
    {
        $this->seedPaidItem();

        $response = $this->actingAs($this->organizer)->get('/organizer/reports/csv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Kode Pesanan', $content);
        $this->assertStringContainsString('300000', $content);
    }

    public function test_organizer_can_open_print_page(): void
    {
        $this->seedPaidItem();

        $this->actingAs($this->organizer)
            ->get('/organizer/reports/print')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organizer/Reports/Print')
                ->has('items', 1)
                ->where('summary.total_quantity', 2));
    }

    public function test_customer_cannot_access_reports(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/organizer/reports')
            ->assertForbidden();
    }

    public function test_organizer_dashboard_includes_sales_data(): void
    {
        $this->seedPaidItem();

        $this->actingAs($this->organizer)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('stats.sales_by_day')
                ->has('stats.top_categories')
                ->has('stats.quota_fill'));
    }
}
