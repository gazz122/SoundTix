<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PhoneUpdateReproTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_update_persists_phone(): void
    {
        $user = User::factory()->create(['phone' => '081111']);

        $this->actingAs(User::factory()->admin()->create())
            ->put("/admin/users/{$user->id}", [
                'name' => $user->name,
                'role' => $user->role->value,
                'is_active' => true,
                'phone' => '999888777',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'phone' => '999888777']);

        $this->actingAs(User::factory()->admin()->create())
            ->get("/admin/users/{$user->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Form')
                ->where('user.phone', '999888777'));
    }
}
