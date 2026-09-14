<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUserPageFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_open_user_list_page(): void
    {
        User::factory()->create(['name' => 'Cari Aku']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data', 2));
    }

    public function test_admin_can_filter_user_list(): void
    {
        User::factory()->organizer()->create(['name' => 'Ucup Organizer']);

        $this->actingAs($this->admin())
            ->get('/admin/users?role=organizer')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->where('filters.role', 'organizer')
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Ucup Organizer'));
    }

    public function test_admin_cannot_access_organizer_event_creation(): void
    {
        $this->actingAs($this->admin())
            ->get(route('organizer.events.create'))
            ->assertForbidden();
    }

    public function test_non_admin_cannot_open_user_list_page(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_admin_can_create_user_via_web_form(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/users', [
                'name' => 'Petugas Gate B',
                'email' => 'gate.b@example.com',
                'phone' => '081234567890',
                'password' => 'secret123',
                'role' => 'gatekeeper',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'gate.b@example.com',
            'role' => 'gatekeeper',
        ]);
    }

    public function test_admin_can_update_user_via_web_form(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($this->admin())
            ->put("/admin/users/{$customer->id}", [
                'name' => 'Nama Baru',
                'role' => 'organizer',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'name' => 'Nama Baru',
            'role' => 'organizer',
        ]);
    }

    public function test_admin_cannot_edit_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get("/admin/users/{$admin->id}/edit")
            ->assertForbidden();

        $this->actingAs($admin)
            ->put("/admin/users/{$admin->id}", ['name' => 'Sialan'])
            ->assertForbidden();
    }
}
