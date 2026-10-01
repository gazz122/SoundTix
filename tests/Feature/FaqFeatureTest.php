<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FaqFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => UserRole::Customer]);
    }

    private function category(array $attributes = []): FaqCategory
    {
        return FaqCategory::factory()->create($attributes);
    }

    public function test_faq_page_is_public_and_renders_published_faqs(): void
    {
        $publishedCategory = $this->category(['name' => 'Pembelian Tiket']);
        $publishedCategory->faqs()->create(Faq::factory()->make(['question' => 'Cara beli tiket?'])->toArray());
        $publishedCategory->faqs()->create(Faq::factory()->make(['question' => 'Bisa bayar pakai apa?'])->toArray());

        $hiddenCategory = $this->category(['name' => 'Kategori Tersembunyi']);
        $hiddenCategory->faqs()->create(Faq::factory()->make(['is_published' => false])->toArray());

        $this->get('/faq')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('FAQ')
                ->has('categories', 1)
                ->where('categories.0.name', 'Pembelian Tiket')
                ->has('categories.0.faqs', 2)
                ->where('categories.0.faqs.0.question', 'Cara beli tiket?'));
    }

    public function test_faq_page_hides_categories_without_published_faqs(): void
    {
        $empty = $this->category();
        $empty->faqs()->create(Faq::factory()->make(['is_published' => false])->toArray());

        $this->get('/faq')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('categories', 0));
    }

    public function test_guest_cannot_access_admin_faq_management(): void
    {
        $this->get('/admin/faqs')->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_admin_faq_management(): void
    {
        $this->actingAs($this->customer())->get('/admin/faqs')->assertForbidden();
    }

    public function test_admin_can_create_category_and_faq(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/faqs/categories', [
            'name' => 'Pembayaran & Refund',
            'icon' => 'CreditCard',
        ])->assertRedirect(route('admin.faqs.index'));

        $category = FaqCategory::where('slug', 'pembayaran-refund')->firstOrFail();
        $this->assertSame('CreditCard', $category->icon);

        $this->actingAs($admin)->post('/admin/faqs', [
            'category_id' => $category->id,
            'question' => 'Bagaimana proses refund?',
            'answer' => 'Refund diproses setelah event dibatalkan.',
            'is_published' => true,
        ])->assertRedirect(route('admin.faqs.index'));

        $this->assertDatabaseHas('faqs', [
            'category_id' => $category->id,
            'question' => 'Bagaimana proses refund?',
            'is_published' => true,
        ]);
    }

    public function test_admin_can_toggle_publish(): void
    {
        $admin = $this->admin();
        $category = $this->category();
        $faq = $category->faqs()->create(Faq::factory()->make(['is_published' => true])->toArray());

        $this->actingAs($admin)->post("/admin/faqs/{$faq->id}/toggle")
            ->assertRedirect(route('admin.faqs.index'));

        $this->assertDatabaseHas('faqs', ['id' => $faq->id, 'is_published' => false]);
    }

    public function test_admin_can_reorder_faqs_within_category(): void
    {
        $admin = $this->admin();
        $category = $this->category();
        $first = $category->faqs()->create(Faq::factory()->make(['order' => 0])->toArray());
        $second = $category->faqs()->create(Faq::factory()->make(['order' => 1])->toArray());

        $this->actingAs($admin)->post('/admin/faqs/reorder', [
            'category_id' => $category->id,
            'ids' => [$second->id, $first->id],
        ])->assertRedirect(route('admin.faqs.index'));

        $this->assertDatabaseHas('faqs', ['id' => $second->id, 'order' => 0]);
        $this->assertDatabaseHas('faqs', ['id' => $first->id, 'order' => 1]);
    }

    public function test_admin_can_delete_category_with_its_faqs(): void
    {
        $admin = $this->admin();
        $category = $this->category();
        $faq = $category->faqs()->create(Faq::factory()->make()->toArray());

        $this->actingAs($admin)->delete("/admin/faqs/categories/{$category->id}")
            ->assertRedirect(route('admin.faqs.index'));

        $this->assertDatabaseMissing('faq_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);
    }
}
