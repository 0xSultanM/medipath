<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 9 performance pass — composite indexes matching the hottest portal
 * queries (pending queue ordering, payment-status invoice lists, active
 * expiry sweeps and doctor request tabs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'idx_prescriptions_status_created');
            $table->index('dispensed_at', 'idx_prescriptions_dispensed_at');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['payment_status', 'created_at'], 'idx_invoices_status_created');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['doctor_id', 'status', 'date'], 'idx_appointments_doctor_status_date');
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->index(['is_active', 'archived_at', 'expiry_date'], 'idx_medicines_active_expiry');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropIndex('idx_prescriptions_status_created');
            $table->dropIndex('idx_prescriptions_dispensed_at');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_status_created');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_appointments_doctor_status_date');
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->dropIndex('idx_medicines_active_expiry');
        });
    }
};