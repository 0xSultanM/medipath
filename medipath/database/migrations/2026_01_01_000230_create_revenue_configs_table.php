<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Effective-dated revenue percentage (PLAN Sprint 3.3). CHECK(0..100)
        // lives in 000320; the "active" row is resolved by effective_from.
        Schema::create('revenue_configs', function (Blueprint $table) {
            $table->id();
            $table->decimal('percentage', 5, 2);
            $table->date('effective_from')->index();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_configs');
    }
};
