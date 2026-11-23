<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            // One-to-one, permanent: patients.user_id is immutable (model guard
            // + unique FK). Patient IDs are never reused (RestrictOnDelete).
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('patient_code', 24)->unique();
            $table->string('name');
            $table->string('phone', 32)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('gender', 24)->nullable();
            $table->string('blood_group', 8)->nullable();
            $table->string('condition')->nullable();
            $table->string('address')->nullable();
            $table->string('walk_in_code', 32)->nullable()->unique();
            $table->boolean('is_walk_in')->default(false)->index();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
