<?php

namespace Tests\Feature;

use App\Enums\RefundStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opens every page each role can actually reach.
 *
 * Feature tests check behaviour one endpoint at a time; this one catches the
 * integration failures that only show up when the whole stack runs together
 * (a query that breaks on real rows, a prop a page expects but no longer gets).
 */
class IntegrationSmokeFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render(): void
    {
        $this->assertPagesRender([
            '/',
            '/faq',
            '/about',
            '/contact',
            '/organizer-guide',
            '/organizer-info',
        ]);
    }

    public function test_customer_pages_render(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $refund = Refund::factory()->create([
            'order_id' => $order->id,
            'status' => RefundStatus::WaitingBankDetails,
        ]);

        $this->actingAs($customer);

        $this->assertPagesRender([
            '/jelajah',
            '/orders',
            '/saved',
            '/notifications',
            "/orders/{$order->order_code}",
            "/refunds/{$refund->id}/account",
        ]);
    }

    public function test_organizer_pages_render(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);

        $this->actingAs($organizer);

        $this->assertPagesRender([
            '/organizer/events',
            '/organizer/events/create',
            "/organizer/events/{$event->id}",
            "/organizer/events/{$event->id}/categories",
            "/organizer/events/{$event->id}/edit",
            '/organizer/payouts',
            '/organizer/refunds',
            '/organizer/reports',
            '/organizer/reports/csv',
            '/organizer/reports/print',
        ]);
    }

    public function test_admin_pages_render(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin);

        $this->assertPagesRender([
            '/admin/events',
            '/admin/faqs',
            '/admin/payouts',
            '/admin/refunds',
            '/admin/refunds/export',
            '/admin/users',
            '/admin/users/create',
            "/admin/users/{$member->id}/edit",
        ]);
    }

    public function test_gatekeeper_scanner_renders(): void
    {
        $this->actingAs(User::factory()->gatekeeper()->create());

        $this->assertPagesRender(['/dashboard/scan']);
    }

    /**
     * @param  array<int, string>  $urls
     */
    private function assertPagesRender(array $urls): void
    {
        foreach ($urls as $url) {
            $response = $this->get($url);

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "GET {$url} returned {$response->getStatusCode()}.",
            );
        }
    }
}
