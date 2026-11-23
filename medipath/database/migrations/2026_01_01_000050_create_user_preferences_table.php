<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // theme: system | light | dark — System→user override chain (G7).
            $table->string('theme', 12)->default('system');
            $table->string('language', 8)->default('en');
            $table->string('timezone', 64)->nullable();
            $table->string('date_format', 24)->nullable();
            $table->json('notification_prefs')->nullable();
            $table->boolean('reduce_motion')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
