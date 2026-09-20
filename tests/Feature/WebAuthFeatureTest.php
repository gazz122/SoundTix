<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebAuthFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_visits_public_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Home/Index')
                    ->where('auth.user.email', $user->email),
            );
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_register_page_renders(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create(['email' => 'customer@example.com']);

        $this->post('/login', [
            'email' => 'customer@example.com',
            'password' => 'salah-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_inactive_account_cannot_login(): void
    {
        User::factory()->inactive()->create([
            'email' => 'suspend@example.com',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'suspend@example.com',
            'password' => 'password',
        ])->assertForbidden();

        $this->assertGuest('web');
    }

    public function test_customer_can_login_and_reach_dashboard(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'customer@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard/Customer'));
    }

    public function test_organizer_reaches_dashboard(): void
    {
        User::factory()->organizer()->create([
            'email' => 'organizer@example.com',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'organizer@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard/Organizer'));
    }

    public function test_gatekeeper_login_redirects_to_scanner(): void
    {
        User::factory()->gatekeeper()->create([
            'email' => 'gatekeeper@example.com',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'gatekeeper@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard.scan'));

        $this->assertAuthenticatedAs(User::where('email', 'gatekeeper@example.com')->first());
    }

    public function test_admin_login_requires_whitelisted_email(): void
    {
        config(['access.admin_emails' => ['owner@example.com']]);

        User::factory()->admin()->create([
            'email' => 'owner@example.com',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs(User::where('email', 'owner@example.com')->first());

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard/Admin'));

        $this->post('/logout');

        User::factory()->admin()->create([
            'email' => 'intruder@example.com',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'intruder@example.com',
            'password' => 'password',
        ])->assertForbidden();

        $this->assertGuest('web');
    }

    public function test_user_can_register_as_customer(): void
    {
        $this->post('/register', [
            'name' => 'Sinta Putri',
            'email' => 'sinta@example.com',
            'phone' => '081234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'sinta@example.com',
            'role' => 'customer',
        ]);

        $this->assertAuthenticated();
    }

    public function test_register_assigned_role_is_ignored(): void
    {
        $this->post('/register', [
            'name' => 'Sinta Putri',
            'email' => 'sinta@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'organizer',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'sinta@example.com',
            'role' => 'customer',
        ]);

        $this->assertAuthenticated();
    }

    public function test_register_rejects_admin_role(): void
    {
        $this->post('/register', [
            'name' => 'Nakal',
            'email' => 'nakal@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'nakal@example.com',
            'role' => 'customer',
        ]);

        $this->assertAuthenticated();
    }

    public function test_register_rejects_gatekeeper_role(): void
    {
        $this->post('/register', [
            'name' => 'Nakal',
            'email' => 'nakal2@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'gatekeeper',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'nakal2@example.com',
            'role' => 'customer',
        ]);

        $this->assertAuthenticated();
    }

    public function test_logout_ends_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertGuest('web');
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
