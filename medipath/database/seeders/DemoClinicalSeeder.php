<?php

namespace Database\Seeders;

use App\Enums\AllergySeverity;
use App\Enums\AppointmentStatus;
use App\Enums\ModerationStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrescriptionStatus;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\EmergencyRule;
use App\Models\InventoryLedger;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientMedication;
use App\Models\Pharmacist;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Review;
use App\Models\SymptomCheckLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Deterministic demo clinical graph: allergies, medication lists, appointments
 * (including a walk-in), prescriptions in both dispensed and pending states,
 * invoices/items, moderated reviews, inventory ledger and notifications.
 * This is what later phases demo against — never lorem ipsum.
 */
class DemoClinicalSeeder extends Seeder
{
    public function run(): void
    {
        $today = now()->startOfDay();

        $admin = User::query()->where('email', 'admin@medipath.test')->firstOrFail();
        $ayeshaUser = User::query()->where('email', 'ayesha.rahman@medipath.test')->firstOrFail();
        $pharmacistUser = User::query()->where('email', 'pharmacist@medipath.test')->firstOrFail();

        $farhana = Patient::query()->where('patient_code', 'PT-1001')->firstOrFail();
        $rafiq = Patient::query()->where('patient_code', 'PT-1002')->firstOrFail();
        $shila = Patient::query()->where('patient_code', 'PT-1003')->firstOrFail();
        $imran = Patient::query()->where('patient_code', 'PT-1004')->firstOrFail();

        $ayesha = Doctor::query()->where('user_id', $ayeshaUser->id)->firstOrFail();
        $kamal = Doctor::query()->whereHas('user', fn ($query) => $query->where('email', 'kamal.hossain@medipath.test'))->firstOrFail();
        $nusrat = Doctor::query()->whereHas('user', fn ($query) => $query->where('email', 'nusrat.jahan@medipath.test'))->firstOrFail();
        $shirin = Doctor::query()->whereHas('user', fn ($query) => $query->where('email', 'shirin.akter@medipath.test'))->firstOrFail();
        $pharmacist = Pharmacist::query()->where('user_id', $pharmacistUser->id)->firstOrFail();

        // A child patient to keep the paediatric demo graph honest.
        $ayaanUser = User::query()->updateOrCreate(
            ['email' => 'ayaan.ahmed@medipath.test'],
            [
                'name' => 'Ayaan Ahmed',
                'phone' => '+880 1812 445566',
                'role' => 'patient',
                'status' => 'approved',
                'verification_status' => 'verified',
                'password' => config('medipath.demo.password'),
                'email_verified_at' => now(),
            ],
        );

        $ayaan = Patient::query()->updateOrCreate(
            ['user_id' => $ayaanUser->id],
            [
                'patient_code' => 'PT-1005',
                'name' => 'Ayaan Ahmed',
                'phone' => '+880 1812 445566',
                'age' => 6,
                'gender' => 'Male',
                'blood_group' => 'O+',
                'condition' => 'Childhood asthma',
                'address' => 'Mirpur, Dhaka',
            ],
        );

        // Claimable walk-in record created by the clinic desk (placeholder user).
        // The registration form can merge it into a new patient account.
        $walkInUser = User::query()->updateOrCreate(
            ['email' => 'walkin.9001@walkin.medipath.local'],
            [
                'name' => 'Walk-in Record 9001',
                'phone' => '+880 1700 009001',
                'role' => 'patient',
                'status' => 'approved',
                'verification_status' => 'verified',
                'password' => Str::random(48),
                'email_verified_at' => now(),
            ],
        );

        $walkInPatient = Patient::query()->updateOrCreate(
            ['user_id' => $walkInUser->id],
            [
                'patient_code' => 'PT-9001',
                'name' => 'Walk-in Record 9001',
                'phone' => '+880 1700 009001',
                'age' => 44,
                'gender' => 'Male',
                'condition' => 'Walk-in blood pressure follow-up',
                'walk_in_code' => 'WI-9001',
                'is_walk_in' => true,
            ],
        );

        PatientAllergy::query()->updateOrCreate(
            ['patient_id' => $walkInPatient->id, 'substance' => 'Penicillin'],
            [
                'allergy_type' => 'medication',
                'severity' => AllergySeverity::Mild,
                'reaction_notes' => 'Rash on a previous walk-in visit.',
                'recorded_by' => $admin->id,
            ],
        );

        // ------------------------------------------------------------ allergies
        $allergies = [
            [$farhana, 'Penicillin', 'medication', AllergySeverity::Severe, 'Lip and facial swelling after amoxicillin at age 24.'],
            [$farhana, 'Shellfish', 'food', AllergySeverity::Moderate, 'Hives within 30 minutes of ingestion.'],
            [$rafiq, 'Sulfonamides', 'medication', AllergySeverity::Moderate, 'Widespread rash with co-trimoxazole.'],
            [$shila, 'House dust mite', 'environmental', AllergySeverity::Mild, 'Sneezing and wheeze in dusty rooms.'],
            [$ayaan, 'Peanuts', 'food', AllergySeverity::LifeThreatening, 'Previous adrenaline use for throat tightness — carries auto-injector.'],
        ];

        foreach ($allergies as [$patient, $substance, $type, $severity, $notes]) {
            PatientAllergy::query()->updateOrCreate(
                ['patient_id' => $patient->id, 'substance' => $substance],
                [
                    'allergy_type' => $type,
                    'severity' => $severity,
                    'reaction_notes' => $notes,
                    'recorded_by' => $admin->id,
                ],
            );
        }

        // ---------------------------------------------------------- medications
        $medications = [
            [$farhana, 'Amlodipine', '5 mg', 'Once daily (morning)', '2024-02-10'],
            [$farhana, 'Aspirin', '75 mg', 'Once daily (after breakfast)', '2025-07-01'],
            [$rafiq, 'Metformin', '500 mg', 'Twice daily with meals', '2023-11-15'],
            [$rafiq, 'Atorvastatin', '20 mg', 'Once daily at night', '2024-01-20'],
            [$shila, 'Salbutamol inhaler', '2 puffs', 'As needed for wheeze', '2025-03-05'],
            [$ayaan, 'Salbutamol inhaler', '2 puffs', 'As needed, spacer advised', '2025-09-12'],
        ];

        foreach ($medications as [$patient, $name, $dosage, $frequency, $start]) {
            PatientMedication::query()->updateOrCreate(
                ['patient_id' => $patient->id, 'medicine_name' => $name, 'dosage' => $dosage],
                [
                    'frequency' => $frequency,
                    'start_date' => $start,
                    'is_active' => true,
                ],
            );
        }

        // ---------------------------------------------------------- appointments
        $appointments = [
            ['APT-3001', $farhana, $ayesha, $today->copy()->subDay(), '09:00', '09:30', AppointmentStatus::Completed, 'Blood pressure review and medication tolerance.', false],
            ['APT-3002', $farhana, $ayesha, $today->copy()->addDay(), '16:30', '17:00', AppointmentStatus::Accepted, 'Palpitations with occasional dizziness; BP log attached at desk.', false],
            ['APT-3003', $rafiq, $kamal, $today->copy()->subDays(10), '10:40', '11:00', AppointmentStatus::Completed, 'Recurring itchy rash on both forearms.', false],
            ['APT-3004', $rafiq, $shirin, $today->copy()->subDays(30), '09:20', '09:40', AppointmentStatus::Completed, 'Diabetes medication review and HbA1c discussion.', false],
            ['APT-3005', $ayaan, $nusrat, $today->copy()->subDays(12), '16:00', '16:15', AppointmentStatus::Completed, 'Night-time wheeze and inhaler technique with spacer.', false],
            ['APT-3006', $shila, $shirin, $today->copy()->subDays(20), '10:00', '10:20', AppointmentStatus::Completed, 'Asthma exacerbation follow-up after viral illness.', false],
            ['APT-3007', $imran, $ayesha, $today->copy()->subDays(5), '09:30', '10:00', AppointmentStatus::Completed, 'Acid reflux with chest discomfort; ECG normal at desk.', false],
            ['APT-3008', $shila, $shirin, $today, '11:00', '11:20', AppointmentStatus::Completed, 'Asthma review and prescription renewal.', false],
            ['APT-3009', $rafiq, $ayesha, $today->copy()->addDays(2), '09:30', '10:00', AppointmentStatus::Pending, 'Blood pressure and cholesterol review; requests morning slot.', false],
            ['APT-3010', $imran, $ayesha, $today, '11:30', '12:00', AppointmentStatus::Completed, 'Walk-in: chest tightness after meals, now settled.', true],
            ['APT-9011', $walkInPatient, $ayesha, $today->copy()->subDays(15), '09:00', '09:30', AppointmentStatus::Completed, 'Walk-in blood pressure check before account registration.', true],
        ];

        foreach ($appointments as [$code, $patient, $doctor, $date, $start, $end, $status, $reason, $walkIn]) {
            Appointment::query()->updateOrCreate(
                ['appointment_code' => $code],
                [
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'date' => $date->toDateString(),
                    'start_time' => $start,
                    'end_time' => $end,
                    'status' => $status,
                    'visit_reason' => $reason,
                    'is_walk_in' => $walkIn,
                    'created_by' => $walkIn ? $ayeshaUser->id : $patient->user_id,
                    'accepted_at' => $status === AppointmentStatus::Pending ? null : $date->copy()->subDay(),
                    'completed_at' => $status === AppointmentStatus::Completed ? $date->copy()->setTime(12, 0) : null,
                ],
            );
        }

        // --------------------------------------------------------- prescriptions
        $dispensedAt = $today->copy()->subDay()->setTime(10, 5);

        $p1 = Prescription::query()->updateOrCreate(
            ['prescription_code' => 'RX-4001'],
            [
                'patient_id' => $farhana->id,
                'doctor_id' => $ayesha->id,
                'appointment_id' => Appointment::query()->where('appointment_code', 'APT-3001')->value('id'),
                'status' => PrescriptionStatus::Dispensed,
                'clinical_notes' => 'Continue amlodipine; add low-dose aspirin for cardiovascular risk. Recheck BP in 4 weeks.',
                'pharmacy_notes' => 'Dispense 30-day supply. Patient reports penicillin allergy — not relevant to these items.',
                'patient_notes' => 'Take amlodipine in the morning and aspirin after breakfast. Report ankle swelling.',
                'safety_snapshot' => [
                    'allergy_check' => ['status' => 'clear', 'checked' => ['Amlodipine', 'Aspirin']],
                    'interaction_check' => ['status' => 'clear', 'pairs_checked' => 1],
                    'stock_check' => ['status' => 'clear'],
                    'generated_at' => now()->toIso8601String(),
                ],
                'ai_safety_summary' => 'No interaction found between amlodipine and low-dose aspirin at these doses. Deterministic checks passed.',
                'ai_status' => 'ok',
                'dispensed_at' => $dispensedAt,
                'dispensed_by' => $pharmacistUser->id,
            ],
        );

        $p2 = Prescription::query()->updateOrCreate(
            ['prescription_code' => 'RX-4002'],
            [
                'patient_id' => $rafiq->id,
                'doctor_id' => $shirin->id,
                'appointment_id' => Appointment::query()->where('appointment_code', 'APT-3004')->value('id'),
                'status' => PrescriptionStatus::Dispensed,
                'clinical_notes' => 'Continue metformin; start atorvastatin for LDL 4.1 mmol/L. Dietary counselling given.',
                'pharmacy_notes' => 'Dispense 30-day supply. No statin myalgia reported so far.',
                'patient_notes' => 'Take metformin with meals to reduce stomach upset. Report muscle pain.',
                'safety_snapshot' => [
                    'allergy_check' => ['status' => 'clear'],
                    'interaction_check' => ['status' => 'review', 'note' => 'Metformin + furosemide is minor; no furosemide on this prescription.'],
                    'stock_check' => ['status' => 'clear'],
                ],
                'ai_safety_summary' => 'No major interaction in this prescription. Monitor renal function annually with metformin.',
                'ai_status' => 'ok',
                'dispensed_at' => $today->copy()->subDays(29)->setTime(11, 20),
                'dispensed_by' => $pharmacistUser->id,
            ],
        );

        $p3 = Prescription::query()->updateOrCreate(
            ['prescription_code' => 'RX-4003'],
            [
                'patient_id' => $shila->id,
                'doctor_id' => $shirin->id,
                'appointment_id' => Appointment::query()->where('appointment_code', 'APT-3008')->value('id'),
                'status' => PrescriptionStatus::PendingDispense,
                'clinical_notes' => 'Asthma partly controlled. Renew inhaler; short steroid course for current exacerbation.',
                'pharmacy_notes' => 'Check stock of salbutamol inhaler; counsel on spacer use.',
                'patient_notes' => 'Use the inhaler with a spacer. Complete the 5-day prednisolone course.',
                'safety_snapshot' => [
                    'allergy_check' => ['status' => 'clear'],
                    'interaction_check' => ['status' => 'review', 'note' => 'Salbutamol + prednisolone: monitor potassium in severe exacerbation.'],
                    'stock_check' => ['status' => 'clear'],
                ],
                'ai_status' => 'fallback',
                'ai_safety_summary' => 'Deterministic checks completed. AI summary unavailable; pharmacist review required.',
            ],
        );

        $p4 = Prescription::query()->updateOrCreate(
            ['prescription_code' => 'RX-4004'],
            [
                'patient_id' => $ayaan->id,
                'doctor_id' => $nusrat->id,
                'appointment_id' => Appointment::query()->where('appointment_code', 'APT-3005')->value('id'),
                'status' => PrescriptionStatus::Dispensed,
                'clinical_notes' => 'Night cough with wheeze. Trial cetirizine for allergic component; continue inhaler.',
                'pharmacy_notes' => 'Paediatric dose confirmed with parent. Peanut allergy noted.',
                'patient_notes' => 'Give cetirizine at bedtime for 7 days. Keep the auto-injector accessible.',
                'safety_snapshot' => [
                    'allergy_check' => ['status' => 'clear', 'note' => 'Peanut allergy does not cross-react with cetirizine.'],
                    'interaction_check' => ['status' => 'clear'],
                    'stock_check' => ['status' => 'clear'],
                ],
                'ai_status' => 'ok',
                'dispensed_at' => $today->copy()->subDays(11)->setTime(17, 10),
                'dispensed_by' => $pharmacistUser->id,
            ],
        );

        // Keep demo dispense latency realistic for reports (the seed normally
        // creates rows at "now", after their historical dispensed_at).
        foreach ([
            [$p1, $dispensedAt->copy()->subHours(4)],
            [$p2, $today->copy()->subDays(29)->setTime(7, 10)],
            [$p4, $today->copy()->subDays(11)->setTime(13, 5)],
        ] as [$prescription, $createdAt]) {
            $prescription->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
        }

        $p5 = Prescription::query()->updateOrCreate(
            ['prescription_code' => 'RX-4005'],
            [
                'patient_id' => $imran->id,
                'doctor_id' => $ayesha->id,
                'appointment_id' => Appointment::query()->where('appointment_code', 'APT-3007')->value('id'),
                'status' => PrescriptionStatus::PendingDispense,
                'clinical_notes' => 'Symptoms consistent with reflux; ECG normal. Trial PPI for 14 days.',
                'pharmacy_notes' => 'Dispense omeprazole 20 mg; advise on timing before breakfast.',
                'patient_notes' => 'Take omeprazole 30 minutes before breakfast. Return if chest pain recurs.',
                'safety_snapshot' => [
                    'allergy_check' => ['status' => 'clear'],
                    'interaction_check' => ['status' => 'clear'],
                    'stock_check' => ['status' => 'clear'],
                ],
                'ai_status' => 'ok',
                'ai_safety_summary' => 'No allergy or interaction flags for omeprazole in this patient profile.',
            ],
        );

        $this->items($p1, [
            ['Amlodipine', '5 mg', 'Once daily (morning)', '30 days', 30, 'After food', 8.00],
            ['Aspirin', '75 mg', 'Once daily (after breakfast)', '30 days', 30, 'After food', 3.00],
        ]);

        $this->items($p2, [
            ['Metformin', '500 mg', 'Twice daily with meals', '30 days', 60, 'With meals', 6.50],
            ['Atorvastatin', '20 mg', 'Once daily at night', '30 days', 30, 'At night', 15.00],
        ]);

        $this->items($p3, [
            ['Salbutamol inhaler', '2 puffs', 'As needed for wheeze', '30 days', 1, 'Use spacer', 220.00],
            ['Prednisolone', '10 mg', 'Once daily', '5 days', 5, 'Morning after food', 5.00],
        ]);

        $this->items($p4, [
            ['Cetirizine', '10 mg', 'Once daily at bedtime', '7 days', 7, 'At bedtime', 3.50],
        ]);

        $this->items($p5, [
            ['Omeprazole', '20 mg', 'Once daily before breakfast', '14 days', 14, 'Before breakfast', 9.00],
        ]);

        // --------------------------------------------------------------- invoices
        $i1 = Invoice::query()->updateOrCreate(
            ['invoice_code' => 'INV-2001'],
            [
                'patient_id' => $farhana->id,
                'pharmacist_id' => $pharmacist->id,
                'prescription_id' => $p1->id,
                'subtotal' => 330.00,
                'discount_kind' => 'fixed',
                'discount_value' => 0,
                'discount' => 0,
                'net_total' => 330.00,
                'currency_code' => 'BDT',
                'payment_method' => 'cash',
                'payment_status' => PaymentStatus::Paid,
                'paid_amount' => 330.00,
                'paid_at' => $dispensedAt,
            ],
        );

        $i2 = Invoice::query()->updateOrCreate(
            ['invoice_code' => 'INV-2002'],
            [
                'patient_id' => $rafiq->id,
                'pharmacist_id' => $pharmacist->id,
                'prescription_id' => $p2->id,
                'subtotal' => 840.00,
                'discount_kind' => 'fixed',
                'discount_value' => 40.00,
                'discount' => 40.00,
                'net_total' => 800.00,
                'currency_code' => 'BDT',
                'payment_method' => 'mobile_banking',
                'payment_status' => PaymentStatus::Paid,
                'paid_amount' => 800.00,
                'paid_at' => $today->copy()->subDays(29)->setTime(11, 35),
            ],
        );

        $i3 = Invoice::query()->updateOrCreate(
            ['invoice_code' => 'INV-2003'],
            [
                'patient_id' => $ayaan->id,
                'pharmacist_id' => $pharmacist->id,
                'prescription_id' => $p4->id,
                'subtotal' => 24.50,
                'discount_kind' => 'fixed',
                'discount_value' => 0,
                'discount' => 0,
                'net_total' => 24.50,
                'currency_code' => 'BDT',
                'payment_method' => 'cash',
                'payment_status' => PaymentStatus::Paid,
                'paid_amount' => 24.50,
                'paid_at' => $today->copy()->subDays(11)->setTime(17, 20),
            ],
        );

        $this->invoiceItems($i1, [
            ['Amlodipine 5 mg tablet', 30, 8.00, 'Amlodipine'],
            ['Aspirin 75 mg tablet', 30, 3.00, 'Aspirin'],
        ]);
        $this->invoiceItems($i2, [
            ['Metformin 500 mg tablet', 60, 6.50, 'Metformin'],
            ['Atorvastatin 20 mg tablet', 30, 15.00, 'Atorvastatin'],
        ]);
        $this->invoiceItems($i3, [
            ['Cetirizine 10 mg tablet', 7, 3.50, 'Cetirizine'],
        ]);

        // ---------------------------------------------------------------- reviews
        $reviews = [
            ['APT-3001', $farhana, $ayesha, 5, 'Dr. Rahman explained the blood-pressure plan clearly and reviewed my home readings. The pharmacy flagged my penicillin allergy before dispensing.', ModerationStatus::Approved],
            ['APT-3003', $rafiq, $kamal, 4, 'Practical advice for the rash and a clear follow-up plan. Waiting time was reasonable.', ModerationStatus::Approved],
            ['APT-3006', $shila, $shirin, 5, 'The asthma action plan was easy to follow and the inhaler technique check helped a lot.', ModerationStatus::Approved],
            ['APT-3005', $ayaan, $nusrat, 5, 'Dr. Jahan was patient with our son and showed us how to use the spacer properly.', ModerationStatus::Approved],
            ['APT-3007', $imran, $ayesha, 5, 'ECG was done quickly and the chest discomfort was explained calmly. Awaiting moderation.', ModerationStatus::Pending],
        ];

        foreach ($reviews as [$appointmentCode, $patient, $doctor, $rating, $comment, $moderation]) {
            Review::query()->updateOrCreate(
                ['appointment_id' => Appointment::query()->where('appointment_code', $appointmentCode)->value('id')],
                [
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'rating' => $rating,
                    'comment' => $comment,
                    'moderation_status' => $moderation,
                    'moderated_at' => $moderation === ModerationStatus::Approved ? now()->subDays(2) : null,
                    'moderated_by' => $moderation === ModerationStatus::Approved ? $admin->id : null,
                ],
            );
        }

        // -------------------------------------------------------- inventory ledger
        $dispensed = [
            'Amlodipine' => [['restock', 0, 500, 'GRN-2601'], ['dispense', 500, 470, 'RX-4001']],
            'Aspirin' => [['restock', 0, 450, 'GRN-2602'], ['dispense', 450, 420, 'RX-4001']],
            'Metformin' => [['restock', 0, 700, 'GRN-2603'], ['dispense', 700, 640, 'RX-4002']],
            'Atorvastatin' => [['restock', 0, 330, 'GRN-2604'], ['dispense', 330, 300, 'RX-4002']],
            'Cetirizine' => [['restock', 0, 727, 'GRN-2605'], ['dispense', 727, 720, 'RX-4004']],
        ];

        foreach (Medicine::query()->get() as $medicine) {
            $entries = $dispensed[$medicine->name] ?? [['restock', 0, $medicine->stock_quantity, 'GRN-SEED']];

            foreach ($entries as [$type, $previous, $new, $reference]) {
                InventoryLedger::query()->updateOrCreate(
                    ['medicine_id' => $medicine->id, 'type' => $type, 'reference' => $reference],
                    [
                        'quantity_change' => $new - $previous,
                        'previous_qty' => $previous,
                        'new_qty' => $new,
                        'actor_id' => $type === 'dispense' ? $pharmacistUser->id : $admin->id,
                        'notes' => $type === 'restock' ? 'Opening stock (seed).' : 'Dispensed against prescription.',
                        'created_at' => $type === 'dispense' ? $dispensedAt : $today->copy()->subWeeks(8),
                    ],
                );
            }
        }

        // ----------------------------------------------------------- notifications
        $notifications = [
            [$farhana->user, 'appointment.accepted', 'appointment', 'Appointment accepted', 'Dr. Ayesha Rahman accepted your request for tomorrow at 4:30 PM.', '/patient/appointments', now()->subHours(5)],
            [$farhana->user, 'prescription.dispensed', 'prescription', 'Prescription dispensed', 'RX-4001 is ready. The invoice is available under My Bills.', '/patient/prescriptions', $dispensedAt],
            [$farhana->user, 'account.welcome', 'account', 'Welcome to MediPath', 'Your patient portal is active. Keep your allergy list up to date.', '/patient/dashboard', now()->subMonths(4)],
            [$rafiq->user, 'appointment.requested', 'appointment', 'Appointment request received', 'Your request with Dr. Ayesha Rahman is pending acceptance.', '/patient/appointments', now()->subHours(30)],
            [$rafiq->user, 'prescription.dispensed', 'prescription', 'Prescription dispensed', 'RX-4002 was dispensed and paid by mobile banking.', '/patient/prescriptions', $today->copy()->subDays(29)->setTime(11, 35)],
            [$shila->user, 'prescription.pending', 'prescription', 'Prescription sent to pharmacy', 'RX-4003 is waiting for the pharmacist safety check.', '/patient/prescriptions', now()->subHours(2)],
            [$ayeshaUser, 'appointment.request', 'appointment', 'New appointment request', 'Rafiq Ahmed requested a slot in 2 days at 9:30 AM.', '/doctor/requests', now()->subHours(30)],
            [$ayeshaUser, 'review.pending', 'account', 'Review awaiting moderation', 'A new review for APT-3007 is waiting for admin moderation.', '/doctor/reviews', now()->subDays(4)],
            [$pharmacistUser, 'prescription.pending', 'prescription', 'Prescription pending dispense', 'RX-4003 (Shila Das) passed deterministic checks and awaits dispensing.', '/pharmacist/prescriptions', now()->subHours(2)],
            [$pharmacistUser, 'stock.low', 'safety', 'Low stock: Diazepam', 'Diazepam 5 mg is below its minimum threshold (24 available, minimum 30).', '/pharmacist/alerts', now()->subHours(8)],
            [$admin, 'approval.pending', 'account', 'Sign-ups awaiting review', '2 sign-up requests (1 doctor, 1 pharmacist) are waiting for approval.', '/admin/approvals', now()->subHours(6)],
            [$admin, 'review.pending', 'account', 'Review awaiting moderation', '1 patient review is waiting in the moderation queue.', '/admin/faq-reviews', now()->subDays(4)],
        ];

        foreach ($notifications as [$user, $type, $category, $title, $message, $url, $createdAt]) {
            Notification::query()->updateOrCreate(
                ['user_id' => $user->id, 'type' => $type, 'title' => $title],
                [
                    'category' => $category,
                    'message' => $message,
                    'action_url' => $url,
                    'read_at' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ],
            );
        }

        // ---------------------------------------------------------- activity log
        $activities = [
            ['system.seeded', 'Demo dataset installed with verified clinical records.', $today->copy()->subMonths(6)],
            ['admin.login', 'Administrator signed in from the clinic network.', now()->subHours(3)],
            ['approval.pending', 'Two sign-up requests received (doctor and pharmacist).', now()->subHours(6)],
            ['review.pending', 'A review for APT-3007 entered the moderation queue.', now()->subDays(4)],
            ['stock.low', 'Diazepam crossed its minimum stock threshold.', now()->subHours(8)],
        ];

        foreach ($activities as [$action, $detail, $createdAt]) {
            ActivityLog::query()->updateOrCreate(
                ['action' => $action, 'created_at' => $createdAt],
                [
                    'actor_id' => $admin->id,
                    'actor_role' => 'admin',
                    'target_type' => null,
                    'target_id' => null,
                    'detail' => ['note' => $detail],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Seeder',
                ],
            );
        }

        // ------------------------------------------------------ symptom check log
        SymptomCheckLog::query()->updateOrCreate(
            ['patient_id' => $farhana->id, 'created_at' => now()->subDays(3)],
            [
                'symptoms' => [
                    'region' => 'head',
                    'keywords' => ['headache', 'dizziness'],
                    'duration' => '3 days',
                    'severity' => 'moderate',
                    'consent' => true,
                ],
                'ai_response' => [
                    'urgency' => 'routine',
                    'summary' => 'Recurring headache with dizziness; blood pressure review is appropriate.',
                    'suggested_specialty' => 'Neurology',
                    'questions' => ['Any visual aura?', 'Is the headache worse in the morning?'],
                ],
                'emergency_triggered' => false,
                'source' => 'ai',
                'ip_address' => '127.0.0.1',
            ],
        );

        SymptomCheckLog::query()->updateOrCreate(
            ['patient_id' => $imran->id, 'created_at' => now()->subDays(5)],
            [
                'symptoms' => [
                    'region' => 'chest',
                    'keywords' => ['chest pain', 'sweating with chest pain'],
                    'duration' => '20 minutes',
                    'severity' => 'severe',
                    'consent' => true,
                ],
                'ai_response' => ['urgency' => 'emergency', 'halted' => true],
                'matched_rules' => ['cardiac'],
                'emergency_triggered' => true,
                'matched_rule_id' => EmergencyRule::query()->where('category', 'Cardiac')->value('id'),
                'source' => 'deterministic_fallback',
                'ip_address' => '127.0.0.1',
            ],
        );
    }

    /** @param array<int, array<int, mixed>> $items */
    private function items(Prescription $prescription, array $items): void
    {
        foreach ($items as [$name, $dosage, $schedule, $duration, $quantity, $instructions, $price]) {
            $medicine = Medicine::query()->where('name', $name)->firstOrFail();

            PrescriptionItem::query()->updateOrCreate(
                ['prescription_id' => $prescription->id, 'medicine_id' => $medicine->id],
                [
                    'dosage' => $dosage,
                    'schedule' => $schedule,
                    'duration' => $duration,
                    'quantity' => $quantity,
                    'instructions' => $instructions,
                    'unit_price_snapshot' => $price,
                ],
            );
        }
    }

    /** @param array<int, array<int, mixed>> $items */
    private function invoiceItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $index => [$description, $quantity, $unitPrice, $medicineName]) {
            $medicine = Medicine::query()->where('name', $medicineName)->first();

            InvoiceItem::query()->updateOrCreate(
                ['invoice_id' => $invoice->id, 'description' => $description],
                [
                    'medicine_id' => $medicine?->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($quantity * $unitPrice, 2),
                ],
            );
        }
    }
}
