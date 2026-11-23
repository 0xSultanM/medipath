<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'clinic_name', 'value' => 'MediPath Central Clinic', 'type' => 'string', 'group' => 'clinic', 'label' => 'Clinic name', 'is_public' => true],
            ['key' => 'clinic_address', 'value' => 'House 42, Road 11, Banani, Dhaka 1213', 'type' => 'string', 'group' => 'clinic', 'label' => 'Clinic address', 'is_public' => true],
            ['key' => 'clinic_phone', 'value' => '+880 2 5566 7788', 'type' => 'string', 'group' => 'clinic', 'label' => 'Clinic phone', 'is_public' => true],
            ['key' => 'currency_code', 'value' => 'BDT', 'type' => 'currency', 'group' => 'locale', 'label' => 'Currency', 'description' => 'ISO 4217 code. Formats every amount via CurrencyFormatter.', 'is_public' => true],
            ['key' => 'locale', 'value' => 'en', 'type' => 'string', 'group' => 'locale', 'label' => 'Locale', 'is_public' => true],
            ['key' => 'timezone', 'value' => 'Asia/Dhaka', 'type' => 'string', 'group' => 'locale', 'label' => 'Timezone', 'is_public' => true],
            ['key' => 'date_format', 'value' => 'd M Y', 'type' => 'string', 'group' => 'locale', 'label' => 'Date format', 'is_public' => true],
            ['key' => 'theme_default', 'value' => 'system', 'type' => 'string', 'group' => 'appearance', 'label' => 'Default theme', 'is_public' => true],
            ['key' => 'patient_auto_approve', 'value' => true, 'type' => 'bool', 'group' => 'registration', 'label' => 'Auto-approve patient registration', 'description' => 'When off, patient sign-ups wait in the approval queue.', 'is_public' => false],
            ['key' => 'cancellation_cutoff_hours', 'value' => 6, 'type' => 'int', 'group' => 'booking', 'label' => 'Cancellation cutoff (hours)', 'is_public' => true],
            ['key' => 'slot_hold_minutes', 'value' => 10, 'type' => 'int', 'group' => 'booking', 'label' => 'Slot hold duration (minutes)', 'is_public' => false],
            ['key' => 'pdf_header', 'value' => 'MediPath Central Clinic · Banani, Dhaka', 'type' => 'string', 'group' => 'documents', 'label' => 'PDF header', 'is_public' => false],
            ['key' => 'pdf_footer', 'value' => 'This document was generated electronically. Verify with the clinic help desk.', 'type' => 'string', 'group' => 'documents', 'label' => 'PDF footer', 'is_public' => false],
            ['key' => 'ai_enabled', 'value' => true, 'type' => 'bool', 'group' => 'ai', 'label' => 'AI assistance enabled', 'description' => 'Disabling uses deterministic FAQ/rule fallbacks only.', 'is_public' => false],
            ['key' => 'ai_model', 'value' => 'gpt-4o-mini', 'type' => 'string', 'group' => 'ai', 'label' => 'AI model', 'is_public' => false],
            ['key' => 'appointment_reminder_hours', 'value' => 24, 'type' => 'int', 'group' => 'notifications', 'label' => 'Appointment reminder lead time (hours)', 'is_public' => false],
        ];

        foreach ($settings as $setting) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }
    }
}
