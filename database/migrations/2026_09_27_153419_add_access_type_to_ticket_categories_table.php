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
        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->enum('access_type', ['ALL_DAYS', 'SINGLE_DAY'])->default('ALL_DAYS')->after('name');
            $table->foreignId('event_day_id')->nullable()->after('access_type')
                ->constrained('event_days')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_day_id');
            $table->dropColumn('access_type');
        });
    }
};
