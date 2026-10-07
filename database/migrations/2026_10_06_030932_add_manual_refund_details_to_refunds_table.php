<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Refunds are settled by hand: an admin transfers the money to the buyer's
     * account and records where it went. Nullable so refunds created before this
     * column existed stay valid.
     */
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->string('bank_name')->nullable()->after('amount');
            $table->string('account_number')->nullable()->after('bank_name');
            $table->string('account_holder_name')->nullable()->after('account_number');
            $table->string('proof_of_transfer')->nullable()->after('account_holder_name');
            $table->text('notes')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropColumn([
                'bank_name',
                'account_number',
                'account_holder_name',
                'proof_of_transfer',
                'notes',
            ]);
        });
    }
};
