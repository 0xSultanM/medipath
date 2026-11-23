<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('doctor_code', 24)->unique();
            $table->foreignId('specialty_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('experience_years')->default(0);
            $table->string('license_number', 80)->unique();
            $table->string('license_authority', 160);
            $table->string('credential_path')->nullable();
            $table->decimal('fee', 12, 2)->default(0);
            $table->decimal('follow_up_fee', 12, 2)->nullable();
            $table->text('bio')->nullable();
            $table->string('clinic_name', 160)->nullable();
            $table->string('clinic_address')->nullable();
            $table->string('room', 40)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('verification_status', 24)->default('unverified')->index();
            $table->text('review_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_featured')->default(false)->index();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['verification_status', 'specialty_id']);
            $table->index('fee');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
