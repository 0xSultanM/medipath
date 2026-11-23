<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('symptom_check_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_key', 64)->nullable()->index();
            $table->json('symptoms');
            $table->json('ai_response')->nullable();
            $table->json('matched_rules')->nullable();
            $table->boolean('emergency_triggered')->default(false)->index();
            $table->foreignId('matched_rule_id')->nullable()->constrained('emergency_rules')->nullOnDelete();
            $table->string('source', 32)->default('ai'); // ai|deterministic_fallback
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });

        // Hashed, single-use password reset tokens (30-minute TTL).
        Schema::create('password_resets', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Backup snapshots metadata for Admin › Data Management (Phase 3).
        Schema::create('backup_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('label', 160);
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status', 20)->default('ready')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_snapshots');
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('symptom_check_logs');
    }
};
