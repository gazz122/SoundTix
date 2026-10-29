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
        Schema::table('event_rundowns', function (Blueprint $table) {
            $table->foreignId('performer_id')
                ->nullable()
                ->after('event_id')
                ->constrained('performers')
                ->nullOnDelete();
            $table->foreignId('event_day_id')
                ->nullable()
                ->after('performer_id')
                ->constrained('event_days')
                ->cascadeOnDelete();
            $table->index(['event_id', 'event_day_id', 'start_time'], 'event_rundowns_schedule_index');
            $table->string('title')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_rundowns', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropIndex('event_rundowns_schedule_index');
            $table->dropForeign(['performer_id']);
            $table->dropForeign(['event_day_id']);
            $table->dropColumn(['event_day_id', 'performer_id']);
            $table->string('title')->nullable(false)->change();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
    }
};
