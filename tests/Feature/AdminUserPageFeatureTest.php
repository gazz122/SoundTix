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

    public function test_admin_can_create_organizer_via_web_form(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/users', [
                'name' => 'Organizer Baru',
                'email' => 'organizer.baru@example.com',
                'phone' => '081234567890',
                'password' => 'secret123',
                'role' => 'organizer',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'organizer.baru@example.com',
            'role' => 'organizer',
        ]);
    }

    public function test_admin_cannot_create_non_organizer_accounts_via_web_form(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/users', [
                'name' => 'Petugas Gate B',
                'email' => 'gate.b@example.com',
                'password' => 'secret123',
                'role' => 'gatekeeper',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'gate.b@example.com']);
    }

    public function test_create_page_only_offers_the_organizer_role(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/users/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Form')
                ->has('roles', 1)
                ->where('roles.0.value', 'organizer'));
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

    public function test_admin_can_delete_a_user_account(): void
    {
        $customer = User::factory()->create(['name' => 'Budi Korban']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete("/admin/users/{$customer->id}")
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', ['id' => $customer->id]);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.email', $admin->email));
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete("/admin/users/{$admin->id}")
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $admin->id]);
    }

    public function test_deleted_user_cannot_login(): void
    {
        $password = 'secret123';
        $customer = User::factory()->create(['email' => 'terhapus@example.com', 'password' => $password]);
        $customer->delete();

        $this->post('/login', [
            'email' => $customer->email,
            'password' => $password,
        ])->assertSessionHasErrors('email');
    }
}
