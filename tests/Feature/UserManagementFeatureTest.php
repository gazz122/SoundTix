<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        User::factory()->create(['name' => 'Cari Aku']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Cari Aku']);
    }

    public function test_admin_can_search_users(): void
    {
        User::factory()->create(['name' => 'Alice Wonder']);

        User::factory()->create(['name' => 'Bob Builder']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/users?search=alice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alice Wonder');
    }

    public function test_admin_can_create_organizer_account(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/users', [
                'name' => 'Organizer Baru',
                'email' => 'organizer.baru@example.com',
                'password' => 'secret123',
                'role' => 'organizer',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'organizer');

        $this->assertDatabaseHas('users', [
            'email' => 'organizer.baru@example.com',
            'role' => 'organizer',
        ]);
    }

    public function test_admin_cannot_create_customer_or_gatekeeper_accounts(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['customer', 'gatekeeper'] as $role) {
            $this->actingAs($admin, 'sanctum')
                ->postJson('/api/v1/admin/users', [
                    'name' => 'Akun '.$role,
                    'email' => $role.'@example.com',
                    'password' => 'secret123',
                    'role' => $role,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('role');
        }

        $this->assertDatabaseMissing('users', ['email' => 'customer@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'gatekeeper@example.com']);
    }

    public function test_admin_cannot_create_another_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/users', [
                'name' => 'Admin Baru',
                'email' => 'admin.baru@example.com',
                'password' => 'secret123',
                'role' => 'admin',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_admin_can_update_user_role_and_active_status(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$customer->id}", [
                'role' => 'organizer',
                'is_active' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.role', 'organizer')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'role' => 'organizer',
            'is_active' => false,
        ]);
    }

    public function test_admin_cannot_demote_self(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$admin->id}", ['role' => 'customer'])
            ->assertUnprocessable();
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$admin->id}", ['is_active' => false])
            ->assertUnprocessable();
    }

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/admin/users')
            ->assertForbidden();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
    }
}
