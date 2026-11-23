<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->string('type', 20)->index(); // restock|dispense|adjustment|return
            $table->integer('quantity_change');
            $table->unsignedInteger('previous_qty');
            $table->unsignedInteger('new_qty');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            // Append-only: no updated_at by design (PLAN §4 immutability).
            $table->timestamp('created_at')->useCurrent();

            $table->index(['medicine_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_ledger');
    }
};
