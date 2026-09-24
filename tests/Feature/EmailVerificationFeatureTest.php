<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailVerificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function sentOtpCode(): string
    {
        $mail = Mail::sent(OtpMail::class)->last();

        return $mail instanceof OtpMail ? $mail->code : '';
    }

    public function test_registration_creates_unverified_user_and_redirects_to_verify_page(): void
    {
        Mail::fake();

        $this->post('/register', [
            'name' => 'Sinta Putri',
            'email' => 'sinta@example.com',
            'phone' => '081234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/verify');

        $user = User::where('email', 'sinta@example.com')->firstOrFail();
        $this->assertFalse($user->is_verified);
        $this->assertNotNull($user->otp_hash);
        $this->assertAuthenticatedAs($user);
        Mail::assertSent(OtpMail::class, 1);
    }

    public function test_verify_page_renders_only_for_unverified_users(): void
    {
        $unverified = User::factory()->unverified()->create();

        $this->actingAs($unverified)
            ->get('/verify')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Verify')->where('email', $unverified->email));

        $verified = User::factory()->create();
        $this->actingAs($verified)->get('/verify')->assertForbidden();
    }

    public function test_correct_otp_verifies_the_account(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->post('/verify/send')->assertSessionHas('status', fn (string $value) => str_contains($value, 'Kode verifikasi baru'));

        $code = $this->sentOtpCode();
        $this->assertSame(6, strlen($code));

        $this->actingAs($user)
            ->post('/verify', ['code' => $code])
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($user->fresh()->is_verified);
        $this->assertNull($user->fresh()->otp_hash);
    }

    public function test_wrong_otp_is_rejected_and_account_stays_unverified(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->post('/verify/send');

        $this->actingAs($user)
            ->post('/verify', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->is_verified);
    }

    public function test_expired_otp_is_rejected(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $user->update(['otp_expires_at' => now()->subMinute(), 'otp_hash' => 'irrelevant']);
        $user->unsetRelations();

        $this->actingAs($user)
            ->post('/verify', ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->is_verified);
    }

    public function test_unverified_customer_cannot_place_an_order(): void
    {
        $event = Event::factory()->published()->create();
        $category = $event->ticketCategories()->create([
            'name' => 'Reguler',
            'price' => 100_000,
            'total_quantity' => 10,
            'available_quantity' => 10,
            'max_per_order' => 5,
        ]);

        $unverified = User::factory()->unverified()->create();

        $this->actingAs($unverified)
            ->post("/events/{$event->slug}/order", [
                'items' => [['category_id' => $category->id, 'quantity' => 1]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $category->fresh()->available_quantity);
    }

    public function test_verified_customer_can_place_an_order(): void
    {
        $event = Event::factory()->published()->create();
        $category = $event->ticketCategories()->create([
            'name' => 'Reguler',
            'price' => 100_000,
            'total_quantity' => 10,
            'available_quantity' => 10,
            'max_per_order' => 5,
        ]);

        $this->actingAs(User::factory()->create())
            ->post("/events/{$event->slug}/order", [
                'items' => [['category_id' => $category->id, 'quantity' => 1]],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_api_registration_returns_unverified_user_and_otp_can_be_confirmed(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'phone' => '081234567890',
        ]);

        $response->assertCreated()->assertJsonPath('user.is_verified', false);

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $token = $user->createToken('soundtix')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/verify/send')->assertOk();
        $code = $this->sentOtpCode();

        $this->withToken($token)
            ->postJson('/api/v1/verify/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.is_verified', true);

        $this->assertTrue($user->fresh()->is_verified);
    }

    public function test_api_verify_confirm_rejects_wrong_code(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/verify/send');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/verify/confirm', ['code' => '111111'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertFalse($user->fresh()->is_verified);
    }
}
