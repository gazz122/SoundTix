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
        Schema::table('events', function (Blueprint $table) {
            $table->enum('status', ['draft', 'pending', 'published', 'rejected', 'pending_cancellation', 'cancelled', 'completed'])
                ->default('draft')
                ->change();
            $table->text('cancellation_reason')->nullable()->after('rejection_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('cancellation_reason');
            $table->enum('status', ['draft', 'pending', 'published', 'rejected', 'cancelled', 'completed'])
                ->default('draft')
                ->change();
        });
    }
};
