<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleDashboardFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_exposes_macro_stats_and_pending_events(): void
    {
        $admin = User::factory()->admin()->create();
        $organizer = User::factory()->organizer()->create();
        Event::factory()->pending()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Event Menunggu Approval',
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Admin')
                ->has('stats.total_users')
                ->has('stats.total_events')
                ->has('stats.pending_events')
                ->has('stats.revenue')
                ->missing('stats.total_orders')
                ->missing('stats.active_tickets')
                ->has('pending_events', 1)
                ->where('pending_events.0.title', 'Event Menunggu Approval')
                ->where('pending_events.0.organizer', $organizer->name));
    }

    public function test_gatekeeper_is_redirected_to_scanner_from_dashboard(): void
    {
        $gatekeeper = User::factory()->gatekeeper()->create();

        $this->actingAs($gatekeeper)
            ->get('/dashboard')
            ->assertRedirect(route('dashboard.scan'));
    }

    public function test_gatekeeper_is_redirected_from_non_sensitive_organizer_routes(): void
    {
        $gatekeeper = User::factory()->gatekeeper()->create();

        $this->actingAs($gatekeeper)
            ->get('/organizer/events')
            ->assertRedirect(route('dashboard.scan'));
    }

    public function test_gatekeeper_cannot_access_sensitive_organizer_routes(): void
    {
        $gatekeeper = User::factory()->gatekeeper()->create();

        $this->actingAs($gatekeeper)
            ->get('/organizer/reports')
            ->assertForbidden();

        $event = Event::factory()->create(['organizer_id' => User::factory()->organizer()->create()->id]);

        $this->actingAs($gatekeeper)
            ->get("/organizer/events/{$event->id}/edit")
            ->assertForbidden();
    }
}
