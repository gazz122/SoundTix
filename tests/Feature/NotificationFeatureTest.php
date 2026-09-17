<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SoundTixNotification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh', ['--no-interaction' => true]);
    }

    public function test_user_can_view_and_mark_owned_notifications_as_read(): void
    {
        $customer = User::factory()->create();
        $customer->notify(new SoundTixNotification([
            'title' => 'Pesanan siap dibayar',
            'message' => 'Selesaikan pembayaran sebelum batas waktu.',
            'type' => 'order_created',
        ]));

        $notification = $customer->fresh()->notifications()->firstOrFail();

        $this->actingAs($customer)
            ->get('/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications/Index')
                ->where('notifications.data.0.data.title', 'Pesanan siap dibayar')
                ->where('notifications.data.0.read_at', null));

        $this->actingAs($customer)
            ->post("/notifications/{$notification->id}/read")
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_notification_cannot_be_read_by_another_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->notify(new SoundTixNotification([
            'title' => 'Pribadi',
            'message' => 'Notifikasi privat.',
        ]));
        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($other)
            ->post("/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }
}
