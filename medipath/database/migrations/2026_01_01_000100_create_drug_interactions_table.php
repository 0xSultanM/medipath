<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_a_id')->constrained('medicines')->cascadeOnDelete();
            $table->foreignId('medicine_b_id')->constrained('medicines')->cascadeOnDelete();
            $table->string('severity', 20)->index(); // minor|moderate|major|contraindicated
            $table->text('description');
            $table->text('management')->nullable();
            $table->timestamps();

            $table->unique(['medicine_a_id', 'medicine_b_id']);
            $table->index(['medicine_b_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_interactions');
    }
};
