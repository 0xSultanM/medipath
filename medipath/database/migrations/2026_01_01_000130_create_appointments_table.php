<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('appointment_code', 24)->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status', 20)->default('pending')->index();
            $table->string('visit_reason', 500);
            $table->string('rejection_reason', 500)->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->unsignedTinyInteger('reschedule_count')->default(0);
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_walk_in')->default(false);

            /*
             * Concurrency guard (PLAN §4): unique(doctor, date, active-slot-key).
             * active_slot_key = "HH:MM" while status ∈ {pending, accepted}, and
             * NULL once the slot is released. MySQL/SQLite both allow repeated
             * NULLs in a unique index, so history rows never collide.
             */
            $table->string('active_slot_key', 5)->nullable();

            $table->timestamps();

            $table->unique(['doctor_id', 'date', 'active_slot_key'], 'uq_doctor_date_active_slot');
            $table->index(['patient_id', 'status', 'date']);
            $table->index(['doctor_id', 'date', 'status']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
