<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->enum('duration_type', ['single', 'multi_day'])->default('single')->after('event_date');
            $table->unsignedTinyInteger('total_days')->nullable()->after('duration_type');
        });

        DB::table('events')->update(['total_days' => 1]);

        $withDays = DB::table('event_days')->pluck('event_id')->all();

        foreach (DB::table('events')->select('id', 'event_date', 'open_gate', 'close_gate')->get() as $event) {
            if (in_array($event->id, $withDays, true)) {
                continue;
            }

            DB::table('event_days')->insert([
                'event_id' => $event->id,
                'day_number' => 1,
                'date' => $event->event_date,
                'open_gate' => $event->open_gate,
                'close_gate' => $event->close_gate,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['duration_type', 'total_days']);
        });
    }
};
