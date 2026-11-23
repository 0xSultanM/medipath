<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('substance', 160);
            $table->string('allergy_type', 40)->default('medication');
            $table->string('severity', 24)->default('moderate')->index();
            $table->text('reaction_notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['patient_id', 'substance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_allergies');
    }
};
