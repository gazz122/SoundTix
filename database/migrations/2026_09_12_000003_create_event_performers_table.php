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
        Schema::create('event_performers', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('performer_id')->constrained('performers')->cascadeOnDelete();
            $table->primary(['event_id', 'performer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_performers');
    }
};
