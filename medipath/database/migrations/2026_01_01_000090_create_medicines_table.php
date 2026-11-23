<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160)->index();
            $table->string('generic_name', 160)->nullable()->index();
            $table->string('category', 80)->index();
            $table->string('strength', 60)->nullable();
            $table->string('form', 40)->default('tablet');
            $table->string('manufacturer', 160)->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            // CHECK stock_quantity >= 0 is enforced in 000320 (MySQL) and by
            // SQLite triggers for the test path.
            $table->integer('stock_quantity')->default(0);
            $table->unsignedInteger('min_stock_threshold')->default(10);
            $table->date('expiry_date')->nullable()->index();
            $table->string('batch_number', 60)->nullable();
            $table->json('side_effects')->nullable();
            $table->text('contraindications')->nullable();
            $table->text('description')->nullable();
            $table->boolean('requires_prescription')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'stock_quantity']);
            $table->index(['expiry_date', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
