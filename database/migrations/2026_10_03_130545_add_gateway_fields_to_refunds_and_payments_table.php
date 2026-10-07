<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('midtrans_transaction_id')->nullable()->unique()->after('transaction_id');
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('order_id')->constrained('payments')->nullOnDelete();
            $table->string('refund_key')->nullable()->unique()->after('amount');
            $table->string('failure_reason')->nullable()->after('status');
            $table->json('gateway_response')->nullable()->after('failure_reason');
            $table->timestamp('processed_at')->nullable()->after('gateway_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropColumn(['payment_id', 'refund_key', 'failure_reason', 'gateway_response', 'processed_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['midtrans_transaction_id']);
            $table->dropColumn('midtrans_transaction_id');
        });
    }
};
