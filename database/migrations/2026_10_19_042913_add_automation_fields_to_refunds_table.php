<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records how a refund is expected to be settled: by the Midtrans refund
     * API, by a Midtrans Iris payout, or by hand. The default stays manual so
     * enabling automation is an explicit, reversible choice.
     */
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->string('refund_gateway', 40)->nullable()->after('refund_key');
            $table->string('gateway_reference_id')->nullable()->after('refund_gateway');
            $table->enum('status', ['pending', 'waiting_bank_details', 'processing', 'completed', 'failed', 'refunded'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])
                ->default('pending')
                ->change();
            $table->dropColumn(['refund_gateway', 'gateway_reference_id']);
        });
    }
};
