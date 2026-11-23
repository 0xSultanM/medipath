<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DB-level defence in depth for invariants the UI also validates:
 * non-negative stock, valid ranges, enum membership, positive quantities.
 *
 * MySQL 8.4 enforces real CHECK constraints. SQLite (used only for the
 * in-memory fallback test matrix) gets equivalent BEFORE INSERT/UPDATE
 * triggers because SQLite cannot ALTER TABLE ADD CONSTRAINT.
 */
return new class extends Migration
{
    /** @var array<int, array{table:string,name:string,mysql:string,sqlite:string}> */
    private array $checks = [
        ['table' => 'medicines', 'name' => 'chk_medicines_stock_nonneg', 'mysql' => 'stock_quantity >= 0', 'sqlite' => 'NEW.stock_quantity >= 0'],
        ['table' => 'medicines', 'name' => 'chk_medicines_min_threshold_nonneg', 'mysql' => 'min_stock_threshold >= 0', 'sqlite' => 'NEW.min_stock_threshold >= 0'],
        ['table' => 'prescription_items', 'name' => 'chk_rx_items_qty_positive', 'mysql' => 'quantity > 0', 'sqlite' => 'NEW.quantity > 0'],
        ['table' => 'invoices', 'name' => 'chk_invoices_subtotal_nonneg', 'mysql' => 'subtotal >= 0', 'sqlite' => 'NEW.subtotal >= 0'],
        ['table' => 'invoices', 'name' => 'chk_invoices_discount_nonneg', 'mysql' => 'discount >= 0 AND discount <= subtotal', 'sqlite' => 'NEW.discount >= 0 AND NEW.discount <= NEW.subtotal'],
        ['table' => 'invoices', 'name' => 'chk_invoices_net_nonneg', 'mysql' => 'net_total >= 0', 'sqlite' => 'NEW.net_total >= 0'],
        ['table' => 'invoices', 'name' => 'chk_invoices_paid_nonneg', 'mysql' => 'paid_amount >= 0', 'sqlite' => 'NEW.paid_amount >= 0'],
        ['table' => 'reviews', 'name' => 'chk_reviews_rating_range', 'mysql' => 'rating BETWEEN 1 AND 5', 'sqlite' => 'NEW.rating BETWEEN 1 AND 5'],
        ['table' => 'revenue_configs', 'name' => 'chk_revenue_pct_range', 'mysql' => 'percentage >= 0 AND percentage <= 100', 'sqlite' => 'NEW.percentage >= 0 AND NEW.percentage <= 100'],
        ['table' => 'inventory_ledger', 'name' => 'chk_ledger_prev_nonneg', 'mysql' => 'previous_qty >= 0', 'sqlite' => 'NEW.previous_qty >= 0'],
        ['table' => 'inventory_ledger', 'name' => 'chk_ledger_new_nonneg', 'mysql' => 'new_qty >= 0', 'sqlite' => 'NEW.new_qty >= 0'],
        ['table' => 'appointments', 'name' => 'chk_appointments_reschedule_nonneg', 'mysql' => 'reschedule_count >= 0', 'sqlite' => 'NEW.reschedule_count >= 0'],
        ['table' => 'doctor_schedules', 'name' => 'chk_schedule_duration_range', 'mysql' => 'slot_duration BETWEEN 5 AND 240', 'sqlite' => 'NEW.slot_duration BETWEEN 5 AND 240'],
        ['table' => 'users', 'name' => 'chk_users_role_enum', 'mysql' => "role IN ('admin','doctor','pharmacist','patient')", 'sqlite' => "NEW.role IN ('admin','doctor','pharmacist','patient')"],
        ['table' => 'users', 'name' => 'chk_users_status_enum', 'mysql' => "status IN ('pending','approved','rejected','suspended','archived')", 'sqlite' => "NEW.status IN ('pending','approved','rejected','suspended','archived')"],
        ['table' => 'appointments', 'name' => 'chk_appointment_status_enum', 'mysql' => "status IN ('pending','accepted','rejected','cancelled','completed','no_show')", 'sqlite' => "NEW.status IN ('pending','accepted','rejected','cancelled','completed','no_show')"],
        ['table' => 'prescriptions', 'name' => 'chk_prescription_status_enum', 'mysql' => "status IN ('draft','pending_dispense','dispensed','cancelled')", 'sqlite' => "NEW.status IN ('draft','pending_dispense','dispensed','cancelled')"],
        ['table' => 'reviews', 'name' => 'chk_review_moderation_enum', 'mysql' => "moderation_status IN ('pending','approved','rejected')", 'sqlite' => "NEW.moderation_status IN ('pending','approved','rejected')"],
        ['table' => 'patient_allergies', 'name' => 'chk_allergy_severity_enum', 'mysql' => "severity IN ('mild','moderate','severe','life_threatening')", 'sqlite' => "NEW.severity IN ('mild','moderate','severe','life_threatening')"],
        ['table' => 'drug_interactions', 'name' => 'chk_interaction_severity_enum', 'mysql' => "severity IN ('minor','moderate','major','contraindicated')", 'sqlite' => "NEW.severity IN ('minor','moderate','major','contraindicated')"],
        ['table' => 'invoices', 'name' => 'chk_payment_status_enum', 'mysql' => "payment_status IN ('pending','paid','refunded')", 'sqlite' => "NEW.payment_status IN ('pending','paid','refunded')"],
        ['table' => 'invoices', 'name' => 'chk_payment_method_enum', 'mysql' => "payment_method IN ('cash','card','mobile_banking','insurance','bank_transfer')", 'sqlite' => "NEW.payment_method IN ('cash','card','mobile_banking','insurance','bank_transfer')"],
    ];

    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        foreach ($this->checks as $check) {
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE `{$check['table']}` ADD CONSTRAINT `{$check['name']}` CHECK ({$check['mysql']})");
            } elseif ($driver === 'sqlite') {
                foreach (['insert', 'update'] as $event) {
                    DB::statement(
                        "CREATE TRIGGER {$check['name']}_{$event} BEFORE ".strtoupper($event)." ON {$check['table']} ".
                        "WHEN NOT ({$check['sqlite']}) BEGIN SELECT RAISE(ABORT, '{$check['name']}'); END"
                    );
                }
            }
        }

        // One active approval request per user, per role.
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `pending_approvals` ADD CONSTRAINT `chk_pending_status_enum` CHECK (status IN ('pending','approved','rejected'))");
            DB::statement("ALTER TABLE `pending_approvals` ADD CONSTRAINT `chk_pending_role_enum` CHECK (requested_role IN ('doctor','pharmacist','patient'))");
        } else {
            foreach (['insert', 'update'] as $event) {
                DB::statement("CREATE TRIGGER chk_pending_status_{$event} BEFORE ".strtoupper($event)." ON pending_approvals WHEN NOT (NEW.status IN ('pending','approved','rejected')) BEGIN SELECT RAISE(ABORT, 'chk_pending_status_enum'); END");
                DB::statement("CREATE TRIGGER chk_pending_role_{$event} BEFORE ".strtoupper($event)." ON pending_approvals WHEN NOT (NEW.requested_role IN ('doctor','pharmacist','patient')) BEGIN SELECT RAISE(ABORT, 'chk_pending_role_enum'); END");
            }
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        foreach ($this->checks as $check) {
            DB::statement("ALTER TABLE `{$check['table']}` DROP CHECK `{$check['name']}`");
        }
        DB::statement('ALTER TABLE `pending_approvals` DROP CHECK `chk_pending_status_enum`');
        DB::statement('ALTER TABLE `pending_approvals` DROP CHECK `chk_pending_role_enum`');
    }
};
