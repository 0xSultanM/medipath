<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->string('dosage', 80);
            $table->string('schedule', 120);
            $table->string('duration', 60);
            $table->unsignedInteger('quantity');
            $table->string('instructions')->nullable();
            // Price snapshot at prescribing time — invoices never re-read a
            // mutable unit price for historical documents.
            $table->decimal('unit_price_snapshot', 12, 2)->default(0);
            $table->timestamps();

            $table->index('medicine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
