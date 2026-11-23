<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('symptom_specialty_map', function (Blueprint $table) {
            $table->id();
            $table->string('symptom_category', 120)->index();
            $table->json('keywords');
            $table->foreignId('specialty_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['symptom_category', 'specialty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('symptom_specialty_map');
    }
};
