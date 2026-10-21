<?php

namespace Tests\Feature;

use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRefundBatchFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_page_is_paginated_so_a_huge_cancellation_can_be_browsed(): void
    {
        $admin = User::factory()->admin()->create();
        Refund::factory()->count(60)->create();

        $this->actingAs($admin)
            ->get('/admin/refunds')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Refunds/Index')
                ->has('refunds.data', 50)
                ->where('refunds.total', 60)
                ->where('refunds.per_page', 50));
    }

    public function test_refund_page_filters_by_status_and_a_complete_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->refund([
            'status' => RefundStatus::Pending,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'Budi Santoso',
        ]);
        $this->refund(['status' => RefundStatus::WaitingBankDetails]);
        $this->refund(['status' => RefundStatus::Completed]);

        $this->actingAs($admin)
            ->get('/admin/refunds?status=pending&ready=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('refunds.data', 1)
                ->where('refunds.data.0.status', 'pending')
                ->where('filters.status', 'pending')
                ->where('filters.ready', true)
                ->where('bulk.count', 1)
                ->where('bulk.total', fn ($total) => $total > 0));
    }

    public function test_export_downloads_only_refunds_that_have_an_account(): void
    {
        $admin = User::factory()->admin()->create();

        $refund = $this->refund([
            'status' => RefundStatus::Pending,
            'amount' => 90000,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'Budi Santoso',
        ]);

        // No destination yet, so it cannot appear in a transfer file.
        $this->refund(['status' => RefundStatus::WaitingBankDetails]);

        $response = $this->actingAs($admin)
            ->get('/admin/refunds/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));

        $this->assertCount(2, $lines, 'One header row plus one transfer.');
        $this->assertSame('bank_name,account_number,account_holder_name,amount,remark', $lines[0]);
        // fputcsv quotes fields that contain a space, which is valid CSV.
        $this->assertSame(
            'BCA,1234567890,"Budi Santoso",90000.00,'.$refund->order->order_code,
            $lines[1],
        );
    }

    public function test_completing_a_batch_settles_only_refunds_with_an_account(): void
    {
        $admin = User::factory()->admin()->create();

        $pending = $this->refund([
            'status' => RefundStatus::Pending,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'Budi Santoso',
        ]);
        $waiting = $this->refund([
            'status' => RefundStatus::WaitingBankDetails,
            'bank_name' => 'BNI',
            'account_number' => '0987654321',
            'account_holder_name' => 'Siti Aminah',
        ]);
        $withoutAccount = $this->refund(['status' => RefundStatus::Pending]);
        $inFlight = $this->refund([
            'status' => RefundStatus::Processing,
            'bank_name' => 'BRI',
            'account_number' => '1122334455',
            'account_holder_name' => 'Andi Wijaya',
        ]);

        $this->actingAs($admin)
            ->post('/admin/refunds/complete-batch')
            ->assertRedirect(route('admin.refunds.index'))
            ->assertSessionHas('success', '2 refund ditandai selesai.');

        $this->assertSame(RefundStatus::Completed, $pending->fresh()->status);
        $this->assertSame(RefundStatus::Completed, $waiting->fresh()->status);
        $this->assertNotNull($pending->fresh()->processed_at);

        // No destination, so nothing could have been transferred.
        $this->assertSame(RefundStatus::Pending, $withoutAccount->fresh()->status);

        // A gateway is still moving this money; a batch must not pay it twice.
        $this->assertSame(RefundStatus::Processing, $inFlight->fresh()->status);
    }

    public function test_batch_completion_keeps_the_active_filter(): void
    {
        $admin = User::factory()->admin()->create();

        $pending = $this->refund([
            'status' => RefundStatus::Pending,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'Budi Santoso',
        ]);
        $failed = $this->refund([
            'status' => RefundStatus::Failed,
            'bank_name' => 'BNI',
            'account_number' => '0987654321',
            'account_holder_name' => 'Siti Aminah',
        ]);

        $this->actingAs($admin)
            ->post('/admin/refunds/complete-batch', ['status' => 'pending'])
            ->assertSessionHas('success', '1 refund ditandai selesai.');

        $this->assertSame(RefundStatus::Completed, $pending->fresh()->status);
        $this->assertSame(RefundStatus::Failed, $failed->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function refund(array $attributes = []): Refund
    {
        return Refund::factory()->create($attributes);
    }
}
