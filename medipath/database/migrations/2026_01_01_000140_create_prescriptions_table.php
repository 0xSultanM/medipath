<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('prescription_code', 24)->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->text('clinical_notes')->nullable();
            $table->text('pharmacy_notes')->nullable();
            $table->text('patient_notes')->nullable();
            $table->json('safety_snapshot')->nullable();
            $table->text('override_justification')->nullable();
            $table->foreignId('override_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('ai_safety_summary')->nullable();
            $table->string('ai_status', 20)->default('not_requested');
            $table->timestamp('dispensed_at')->nullable();
            $table->foreignId('dispensed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['doctor_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
