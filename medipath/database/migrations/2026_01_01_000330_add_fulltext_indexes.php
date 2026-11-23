<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MySQL FULLTEXT indexes powering Laravel Scout / search (PLAN §2).
 * Guarded so SQLite (fallback test path) skips them cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `medicines` ADD FULLTEXT `ft_medicines_search` (`name`, `generic_name`, `description`)');
        DB::statement('ALTER TABLE `faqs` ADD FULLTEXT `ft_faqs_search` (`question`, `answer`, `keywords`)');
        DB::statement('ALTER TABLE `patients` ADD FULLTEXT `ft_patients_search` (`name`, `phone`, `condition`)');
        DB::statement('ALTER TABLE `invoices` ADD FULLTEXT `ft_invoices_search` (`invoice_code`)');
        DB::statement('ALTER TABLE `doctors` ADD FULLTEXT `ft_doctors_search` (`bio`, `clinic_name`)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `medicines` DROP INDEX `ft_medicines_search`');
        DB::statement('ALTER TABLE `faqs` DROP INDEX `ft_faqs_search`');
        DB::statement('ALTER TABLE `patients` DROP INDEX `ft_patients_search`');
        DB::statement('ALTER TABLE `invoices` DROP INDEX `ft_invoices_search`');
        DB::statement('ALTER TABLE `doctors` DROP INDEX `ft_doctors_search`');
    }
};
