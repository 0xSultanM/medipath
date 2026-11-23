<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_rules', function (Blueprint $table) {
            $table->id();
            $table->string('category', 60)->index(); // cardiac|respiratory|stroke|…
            $table->string('title', 180);
            $table->json('symptom_keywords');
            $table->string('severity', 20)->default('critical');
            $table->text('action_directive');
            $table->string('hotline_key', 40)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_rules');
    }
};
