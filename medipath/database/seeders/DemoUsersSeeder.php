<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Doctor;
use App\Models\DoctorLeave;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\PendingApproval;
use App\Models\Pharmacist;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) config('medipath.demo.password');

        // ------------------------------------------------------------- Admin
        $admin = $this->user([
            'name' => 'Sultana Ahmed',
            'email' => 'admin@medipath.test',
            'phone' => '+880 1711 220011',
            'role' => UserRole::Admin,
            'status' => AccountStatus::Approved,
            'verification_status' => VerificationStatus::Verified,
        ], $password);

        $this->preferences($admin, 'system');

        // ------------------------------------------------------------ Doctors
        $ayesha = $this->doctor($password, [
            'name' => 'Dr. Ayesha Rahman',
            'email' => 'ayesha.rahman@medipath.test',
            'phone' => '+880 1811 440022',
            'code' => 'DOC-1001',
            'specialty' => 'Cardiology',
            'experience' => 12,
            'license' => 'BMDC-A-48213',
            'authority' => 'Bangladesh Medical & Dental Council',
            'fee' => 1200.00,
            'bio' => 'Consultant cardiologist focused on hypertension, stable angina and preventive heart care. MBBS, FCPS (Cardiology).',
            'clinic' => 'MediPath Central Clinic',
            'room' => 'Room 204',
            'verified' => true,
            'featured' => true,
        ], $admin);

        $kamal = $this->doctor($password, [
            'name' => 'Dr. Kamal Hossain',
            'email' => 'kamal.hossain@medipath.test',
            'phone' => '+880 1911 550033',
            'code' => 'DOC-1002',
            'specialty' => 'Dermatology',
            'experience' => 8,
            'license' => 'BMDC-A-51908',
            'authority' => 'Bangladesh Medical & Dental Council',
            'fee' => 900.00,
            'bio' => 'Dermatologist treating eczema, acne, psoriasis and drug rashes. MBBS, DDV.',
            'clinic' => 'MediPath Central Clinic',
            'room' => 'Room 108',
            'verified' => true,
            'featured' => true,
        ], $admin);

        $nusrat = $this->doctor($password, [
            'name' => 'Dr. Nusrat Jahan',
            'email' => 'nusrat.jahan@medipath.test',
            'phone' => '+880 1611 660044',
            'code' => 'DOC-1003',
            'specialty' => 'Pediatrics',
            'experience' => 10,
            'license' => 'BMDC-A-39774',
            'authority' => 'Bangladesh Medical & Dental Council',
            'fee' => 1000.00,
            'bio' => 'Paediatrician with a focus on newborn follow-up, childhood asthma and vaccination schedules.',
            'clinic' => 'MediPath Central Clinic',
            'room' => 'Room 112',
            'verified' => true,
            'featured' => true,
        ], $admin);

        $shirin = $this->doctor($password, [
            'name' => 'Dr. Shirin Akter',
            'email' => 'shirin.akter@medipath.test',
            'phone' => '+880 1511 770055',
            'code' => 'DOC-1004',
            'specialty' => 'General Medicine',
            'experience' => 6,
            'license' => 'BMDC-A-60221',
            'authority' => 'Bangladesh Medical & Dental Council',
            'fee' => 700.00,
            'bio' => 'General physician managing diabetes, asthma follow-up and common infections.',
            'clinic' => 'MediPath Central Clinic',
            'room' => 'Room 101',
            'verified' => true,
            'featured' => false,
        ], $admin);

        $rezaul = $this->doctor($password, [
            'name' => 'Dr. Rezaul Karim',
            'email' => 'rezaul.karim@medipath.test',
            'phone' => '+880 1311 880066',
            'code' => 'DOC-1005',
            'specialty' => 'Neurology',
            'experience' => 15,
            'license' => 'BMDC-A-30441',
            'authority' => 'Bangladesh Medical & Dental Council',
            'fee' => 1500.00,
            'bio' => 'Neurologist with an interest in migraine, epilepsy and stroke rehabilitation.',
            'clinic' => 'MediPath Central Clinic',
            'room' => 'Room 301',
            'verified' => false,
            'featured' => false,
            'status' => AccountStatus::Pending,
            'verification' => VerificationStatus::Pending,
        ], $admin);

        $this->schedules($ayesha, [
            [6, '09:00', '13:00', 30, '11:00', '11:30'],
            [0, '09:00', '13:00', 30, '11:00', '11:30'],
            [1, '16:00', '20:00', 30, null, null],
            [2, '09:00', '13:00', 30, '11:00', '11:30'],
            [3, '16:00', '20:00', 30, null, null],
        ]);

        $this->schedules($kamal, [
            [0, '10:00', '14:00', 20, '12:00', '12:20'],
            [1, '10:00', '14:00', 20, '12:00', '12:20'],
            [2, '10:00', '14:00', 20, '12:00', '12:20'],
            [3, '10:00', '14:00', 20, '12:00', '12:20'],
            [4, '10:00', '13:00', 20, null, null],
        ]);

        $this->schedules($nusrat, [
            [6, '15:00', '20:00', 15, null, null],
            [0, '15:00', '20:00', 15, null, null],
            [1, '15:00', '20:00', 15, null, null],
            [2, '15:00', '20:00', 15, null, null],
            [3, '15:00', '20:00', 15, null, null],
        ]);

        $this->schedules($shirin, [
            [6, '09:00', '14:00', 20, '11:00', '11:20'],
            [1, '09:00', '14:00', 20, '11:00', '11:20'],
            [3, '09:00', '14:00', 20, '11:00', '11:20'],
        ]);

        // Leave is date-ranged and leave-aware availability reads it.
        $leaveStart = Carbon::now()->next(Carbon::WEDNESDAY)->startOfDay();
        DoctorLeave::query()->updateOrCreate(
            ['doctor_id' => $ayesha->id, 'start_date' => $leaveStart->toDateString(), 'end_date' => $leaveStart->copy()->addDays(2)->toDateString()],
            [
                'reason' => 'Cardiology conference (Dhaka)',
                'status' => 'approved',
                'created_by' => $ayesha->user_id,
            ],
        );

        DoctorLeave::query()->updateOrCreate(
            ['doctor_id' => $kamal->id, 'start_date' => Carbon::now()->addDays(10)->toDateString(), 'end_date' => Carbon::now()->addDays(11)->toDateString()],
            [
                'reason' => 'Family leave',
                'status' => 'approved',
                'created_by' => $kamal->user_id,
            ],
        );

        // --------------------------------------------------------- Pharmacist
        $pharmacistUser = $this->user([
            'name' => 'Nadia Tabassum',
            'email' => 'pharmacist@medipath.test',
            'phone' => '+880 1411 990077',
            'role' => UserRole::Pharmacist,
            'status' => AccountStatus::Approved,
            'verification_status' => VerificationStatus::Verified,
        ], $password);

        Pharmacist::query()->updateOrCreate(
            ['user_id' => $pharmacistUser->id],
            [
                'pharmacist_code' => 'PHA-1001',
                'license_number' => 'BMDC-P-77310',
                'license_authority' => 'Bangladesh Medical & Dental Council',
                'pharmacy_name' => 'MediPath Pharmacy',
                'approved_at' => now()->subMonths(6),
                'approved_by' => $admin->id,
            ],
        );

        $this->preferences($pharmacistUser, 'light');

        // ------------------------------------------------------------ Patients
        $farhana = $this->patient($password, [
            'name' => 'Farhana Karim',
            'email' => 'patient@medipath.test',
            'phone' => '+880 1712 334455',
            'code' => 'PT-1001',
            'age' => 32,
            'gender' => 'Female',
            'blood' => 'B+',
            'condition' => 'Hypertension',
            'address' => 'Dhanmondi, Dhaka',
        ]);

        $rafiq = $this->patient($password, [
            'name' => 'Rafiq Ahmed',
            'email' => 'rafiq.ahmed@medipath.test',
            'phone' => '+880 1812 445566',
            'code' => 'PT-1002',
            'age' => 45,
            'gender' => 'Male',
            'blood' => 'O+',
            'condition' => 'Type 2 diabetes',
            'address' => 'Mirpur, Dhaka',
        ]);

        $shila = $this->patient($password, [
            'name' => 'Shila Das',
            'email' => 'shila.das@medipath.test',
            'phone' => '+880 1912 556677',
            'code' => 'PT-1003',
            'age' => 28,
            'gender' => 'Female',
            'blood' => 'A+',
            'condition' => 'Asthma',
            'address' => 'Uttara, Dhaka',
        ]);

        $imran = $this->patient($password, [
            'name' => 'Imran Chowdhury',
            'email' => 'imran.chowdhury@medipath.test',
            'phone' => '+880 1612 667788',
            'code' => 'PT-1004',
            'age' => 38,
            'gender' => 'Male',
            'blood' => 'AB+',
            'condition' => 'Acid reflux',
            'address' => 'Banani, Dhaka',
            'walk_in_code' => 'WI-4821',
            'is_walk_in' => true,
        ]);

        $this->preferences($farhana->user, 'system');
        $this->preferences($rafiq->user, 'light');
        $this->preferences($shila->user, 'dark');
        $this->preferences($imran->user, 'system');

        // ------------------------------------------- Pending sign-up requests
        $tanvir = $this->user([
            'name' => 'Dr. Tanvir Alam',
            'email' => 'tanvir.alam@medipath.test',
            'phone' => '+880 1512 778899',
            'role' => UserRole::Doctor,
            'status' => AccountStatus::Pending,
            'verification_status' => VerificationStatus::Pending,
        ], $password);

        $tanvirDoctor = Doctor::query()->updateOrCreate(
            ['user_id' => $tanvir->id],
            [
                'doctor_code' => 'DOC-1006',
                'specialty_id' => Specialty::query()->where('name', 'Orthopaedics')->value('id'),
                'experience_years' => 9,
                'license_number' => 'BMDC-A-71102',
                'license_authority' => 'Bangladesh Medical & Dental Council',
                'fee' => 1100.00,
                'bio' => 'Orthopaedic surgeon, sports injuries and joint replacement.',
                'clinic_name' => 'MediPath Central Clinic',
                'verification_status' => VerificationStatus::Pending,
            ],
        );

        PendingApproval::query()->updateOrCreate(
            ['user_id' => $tanvir->id, 'status' => 'pending'],
            [
                'requested_role' => 'doctor',
                'license_details' => [
                    'license_number' => 'BMDC-A-71102',
                    'authority' => 'Bangladesh Medical & Dental Council',
                    'specialty' => 'Orthopaedics',
                    'experience_years' => 9,
                ],
                'credential_path' => 'credentials/demo/tanvir-alam-license.pdf',
            ],
        );

        $sadia = $this->user([
            'name' => 'Sadia Islam',
            'email' => 'sadia.islam@medipath.test',
            'phone' => '+880 1312 889900',
            'role' => UserRole::Pharmacist,
            'status' => AccountStatus::Pending,
            'verification_status' => VerificationStatus::Pending,
        ], $password);

        Pharmacist::query()->updateOrCreate(
            ['user_id' => $sadia->id],
            [
                'pharmacist_code' => 'PHA-1002',
                'license_number' => 'BMDC-P-81220',
                'license_authority' => 'Bangladesh Medical & Dental Council',
                'pharmacy_name' => 'MediPath Pharmacy',
            ],
        );

        PendingApproval::query()->updateOrCreate(
            ['user_id' => $sadia->id, 'status' => 'pending'],
            [
                'requested_role' => 'pharmacist',
                'license_details' => [
                    'license_number' => 'BMDC-P-81220',
                    'authority' => 'Bangladesh Medical & Dental Council',
                ],
                'credential_path' => 'credentials/demo/sadia-islam-license.pdf',
            ],
        );

        $this->preferences($tanvir, 'system');
        $this->preferences($sadia, 'system');
    }

    /** @param array<string, mixed> $attributes */
    private function user(array $attributes, string $password): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $attributes['email']],
            [
                ...$attributes,
                'password' => $password,
                'email_verified_at' => now(),
                'locale' => 'en',
                'timezone' => 'Asia/Dhaka',
            ],
        );

        return $user;
    }

    private function preferences(User $user, string $theme): void
    {
        UserPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'theme' => $theme,
                'language' => 'en',
                'timezone' => 'Asia/Dhaka',
                'date_format' => 'd M Y',
                'notification_prefs' => [
                    'appointments' => true,
                    'prescriptions' => true,
                    'billing' => true,
                    'safety' => true,
                ],
                'reduce_motion' => false,
            ],
        );
    }

    /** @param array<string, mixed> $attributes */
    private function doctor(string $password, array $attributes, User $admin): Doctor
    {
        $user = $this->user([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'phone' => $attributes['phone'],
            'role' => UserRole::Doctor,
            'status' => $attributes['status'] ?? AccountStatus::Approved,
            'verification_status' => $attributes['verification'] ?? VerificationStatus::Verified,
        ], $password);

        $this->preferences($user, 'light');

        $specialty = Specialty::query()->where('name', $attributes['specialty'])->firstOrFail();

        return Doctor::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'doctor_code' => $attributes['code'],
                'specialty_id' => $specialty->id,
                'experience_years' => $attributes['experience'],
                'license_number' => $attributes['license'],
                'license_authority' => $attributes['authority'],
                'credential_path' => 'credentials/demo/'.strtolower(str_replace(['.', ' '], ['', '-'], $attributes['name'])).'-license.pdf',
                'fee' => $attributes['fee'],
                'bio' => $attributes['bio'],
                'clinic_name' => $attributes['clinic'],
                'clinic_address' => 'House 42, Road 11, Banani, Dhaka 1213',
                'room' => $attributes['room'],
                'verification_status' => $attributes['verified'] ? VerificationStatus::Verified : VerificationStatus::Pending,
                'verified_at' => $attributes['verified'] ? now()->subMonths(4) : null,
                'verified_by' => $attributes['verified'] ? $admin->id : null,
                'is_featured' => $attributes['featured'],
            ],
        );
    }

    /** @param array<int, array<int, mixed>> $blocks */
    private function schedules(Doctor $doctor, array $blocks): void
    {
        foreach ($blocks as [$day, $start, $end, $duration, $breakStart, $breakEnd]) {
            DoctorSchedule::query()->updateOrCreate(
                ['doctor_id' => $doctor->id, 'day_of_week' => $day, 'start_time' => $start],
                [
                    'end_time' => $end,
                    'slot_duration' => $duration,
                    'break_start' => $breakStart,
                    'break_end' => $breakEnd,
                    'is_active' => true,
                ],
            );
        }
    }

    /** @param array<string, mixed> $attributes */
    private function patient(string $password, array $attributes): Patient
    {
        $user = $this->user([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'phone' => $attributes['phone'],
            'role' => UserRole::Patient,
            'status' => AccountStatus::Approved,
            'verification_status' => VerificationStatus::Verified,
        ], $password);

        return Patient::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'patient_code' => $attributes['code'],
                'name' => $attributes['name'],
                'phone' => $attributes['phone'],
                'age' => $attributes['age'],
                'gender' => $attributes['gender'],
                'blood_group' => $attributes['blood'],
                'condition' => $attributes['condition'],
                'address' => $attributes['address'],
                'walk_in_code' => $attributes['walk_in_code'] ?? null,
                'is_walk_in' => $attributes['is_walk_in'] ?? false,
            ],
        );
    }
}
