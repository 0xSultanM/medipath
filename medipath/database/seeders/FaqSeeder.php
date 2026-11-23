<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            ['How do I book an appointment?', 'Open Find Doctors, choose a verified doctor, pick an available slot, and describe your visit reason. Your request is sent to the doctor for acceptance. You will see the status in My Appointments.', 'Booking', 'book appointment slot request doctor', 1],
            ['Can I book without an account?', 'You can browse the directory and doctor profiles as a guest, but booking requires a patient account so the clinic can link your records safely.', 'Booking', 'guest account register booking', 2],
            ['Why is my registration pending?', 'Doctor and pharmacist accounts wait for license verification by the clinic administrator. You can check the status on the pending approval screen; the usual review time is under 24 hours.', 'Account', 'pending approval verification license', 3],
            ['How do I cancel or reschedule?', 'Go to My Appointments and choose Cancel or Reschedule. Cancellations are allowed until the cutoff shown on the card. Rescheduling keeps your visit reason and releases the old slot.', 'Booking', 'cancel reschedule cutoff', 4],
            ['Where can I download my prescription?', 'Open My Prescriptions and use the Rx PDF button on the prescription. The PDF is generated server-side and is only available to you and your care team.', 'Prescriptions', 'prescription pdf download', 5],
            ['Are all prescriptions safety checked?', 'Yes. Before dispensing, the pharmacist sees deterministic allergy, drug-interaction, expiry and stock checks. Any override requires a written justification and is recorded in the audit log.', 'Prescriptions', 'allergy interaction safety pharmacist', 6],
            ['How do I pay my bill?', 'Bills are created when medicines are dispensed. You can view totals and download the invoice PDF under My Bills. Payment method is recorded by the pharmacy.', 'Billing', 'bill invoice payment receipt', 7],
            ['Can I get a refund?', 'Refunds are handled by the pharmacy with a recorded reason. Ask the pharmacy desk with your invoice number; the refund appears on the invoice history.', 'Billing', 'refund invoice billing', 8],
            ['Is the AI symptom checker a diagnosis?', 'No. It provides general guidance and suggests a specialty. Red-flag symptoms are matched by deterministic rules that take priority over AI, and emergency guidance never waits for AI.', 'AI', 'ai symptom checker diagnosis emergency', 9],
            ['What happens in an emergency?', 'Call the national emergency number immediately. The Emergency Info page lists one-tap numbers and red-flag guidance and remains available once cached on your device.', 'Emergency', 'emergency red flag call ambulance', 10],
            ['How do I update my allergies?', 'Open Allergies & Medicines in your portal. Severe and life-threatening allergies are highlighted to every clinician who opens your chart.', 'Account', 'allergy medication safety profile', 11],
            ['How are reviews moderated?', 'Only patients with a completed appointment can review a doctor. Reviews are checked by an administrator before publication; you can see the moderation status in My Reviews.', 'Reviews', 'review rating moderation feedback', 12],
            ['Can I choose my doctor?', 'Yes. Search by specialty, availability, rating or fee. The AI suggestion is only a starting point; you always choose the doctor.', 'Booking', 'choose doctor search filter specialty', 13],
            ['Is my credential document public?', 'No. Licenses are stored on a private disk and are only viewable by the administrator during verification.', 'Account', 'license credential privacy upload', 14],
            ['What if a slot is taken while I book?', 'The clinic enforces one active appointment per doctor, date and time at the database level. If another patient takes the slot first, you will see a clear error and can reload availability without losing your reason.', 'Booking', 'slot taken concurrency error', 15],
        ];

        foreach ($faqs as [$question, $answer, $category, $keywords, $sort]) {
            Faq::query()->updateOrCreate(
                ['question' => $question],
                [
                    'answer' => $answer,
                    'category' => $category,
                    'keywords' => $keywords,
                    'is_published' => true,
                    'sort_order' => $sort,
                ],
            );
        }
    }
}
