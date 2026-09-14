<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_receive_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'phone' => '081234567890',
            'role' => 'customer',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'budi@example.com')
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonStructure(['token', 'user']);

        $this->assertDatabaseHas('users', [
            'email' => 'budi@example.com',
            'role' => 'customer',
        ]);
    }

    public function test_organizer_can_register(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Promotor Aneka',
            'email' => 'promotor@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'organizer',
        ]);

        $response->assertCreated()->assertJsonPath('user.role', 'organizer');
    }

    public function test_register_rejects_admin_role(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Nakal',
            'email' => 'nakal@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'admin',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_register_rejects_gatekeeper_role(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Nakal',
            'email' => 'nakal@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'gatekeeper',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'sama@example.com']);

        $response = $this->postJson('/api/v1/register', [
            'name' => 'Duplikat',
            'email' => 'sama@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_user_can_login_and_receive_token(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'customer@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'customer@example.com')
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create(['email' => 'customer@example.com']);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'customer@example.com',
            'password' => 'salah-password',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_login_rejects_inactive_account(): void
    {
        User::factory()->inactive()->create(['email' => 'suspend@example.com']);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'suspend@example.com',
            'password' => 'password',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_admin_login_requires_whitelisted_email(): void
    {
        config(['access.admin_emails' => ['owner@example.com']]);

        User::factory()->admin()->create([
            'email' => 'owner@example.com',
            'password' => 'secret123',
        ]);

        $whitelisted = $this->postJson('/api/v1/login', [
            'email' => 'owner@example.com',
            'password' => 'secret123',
        ]);

        $whitelisted->assertOk();

        User::factory()->admin()->create([
            'email' => 'intruder@example.com',
            'password' => 'secret123',
        ]);

        $notWhitelisted = $this->postJson('/api/v1/login', [
            'email' => 'intruder@example.com',
            'password' => 'secret123',
        ]);

        $notWhitelisted->assertForbidden();
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('soundtix')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJson(['message' => 'Berhasil keluar.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }
}
