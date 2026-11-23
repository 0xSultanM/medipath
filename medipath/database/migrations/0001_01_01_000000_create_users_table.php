<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 32)->nullable()->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // PLAN §4: role + status + verification_status live on users.
            // Values are validated again by DB CHECK constraints (see 000320).
            $table->string('role', 20)->default('patient')->index();
            $table->string('status', 20)->default('pending')->index();
            $table->string('verification_status', 24)->default('unverified')->index();

            $table->string('avatar_path')->nullable();
            $table->string('locale', 8)->nullable();
            $table->string('timezone', 64)->nullable();

            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['role', 'status']);
            $table->index(['role', 'verification_status']);
            $table->index('created_at');
        });

        // Laravel session table (DB fallback). Production uses Redis sessions;
        // the table is kept for Admin Security › DB sessions (PLAN Phase 3.10).
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
