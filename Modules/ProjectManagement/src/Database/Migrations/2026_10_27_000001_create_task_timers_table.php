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
        Schema::create('task_timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();

            // Who started / stopped the session (kept if the user is later deleted)
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('stopped_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('started_at');
            $table->timestamp('stopped_at')->nullable();             // NULL = timer is running
            $table->unsignedInteger('duration_seconds')->nullable(); // filled when stopped
            $table->text('note')->nullable();                        // work note / description

            $table->timestamps();

            $table->index(['task_id', 'stopped_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_timers');
    }
};
