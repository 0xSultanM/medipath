# Part 6 — Doctor Portal (complete)

Visual acceptance: `design-out/prototypes/doctor/01-dashboard.html` … `08-reviews.html`.
Every route is behind `auth + role:doctor + verified.doctor`; the doctor is resolved
from `auth()->user()->doctor`, never from a posted id.

## Route map

| Screen | Route | Controller |
|---|---|---|
| Dashboard metrics + timeline | `/doctor/dashboard` | `Doctor\WorkspaceController@dashboard` |
| Weekly schedule + leave | `/doctor/schedule` | `Doctor\WorkspaceController@schedule` |
| Add / toggle availability block | `POST /doctor/schedule/blocks`, `…/{block}/toggle` | `WorkspaceController` |
| Add / cancel leave | `POST /doctor/schedule/leaves`, `…/{leave}/cancel` | `WorkspaceController` |
| Appointment requests | `/doctor/requests` | `WorkspaceController@requests` |
| Accept/reject/complete/cancel | `POST /doctor/requests/{appointment}/…` | `WorkspaceController` |
| My patients | `/doctor/patients` | `Doctor\PatientController@index` |
| Patient chart | `/doctor/patients/{patient}` | `Doctor\PatientController@show` |
| Walk-in registration | `/doctor/walk-in` | `Doctor\WalkInController` |
| Prescription builder | `/doctor/prescriptions/new?patient=` | `Doctor\PrescriptionController@create` |
| Live safety check | `POST /doctor/prescriptions/safety-check` | `PrescriptionController@safetyCheck` |
| Save prescription | `POST /doctor/prescriptions` | `PrescriptionController@store` |
| Prescription PDF | `/doctor/prescriptions/{prescription}/pdf` | `PrescriptionController@pdf` |
| Live medicine search | `/doctor/medicines/search`, `/doctor/medicines/{medicine}` | `Doctor\MedicineController` |
| My reviews | `/doctor/reviews` | `Doctor\ProfileController@reviews` |
| Profile/settings | `/doctor/settings` + profile/password/preferences routes | `Doctor\ProfileController` |

## What ships

* **Dashboard** — today's appointments/completed, pending requests, weekly prescriptions, approved
  rating; today's timeline and pending queue with quick actions and leave warning.
* **Schedule & leave** — weekday blocks with slot duration/break, overlap rejection, enable/disable;
  approved leave hides future slots and atomically cancels overlapping pending/accepted
  appointments with patient notifications.
* **Requests** — tabs for pending/today/upcoming/completed/cancelled, request age, accept, reject with
  reason, mark completed and cancel-with-notice. Every transition writes an audit entry and notifies
  the patient.
* **Patients** — only patients with an appointment or prescription by this doctor; search, last-visit
  and allergy filters; safety flags.
* **Chart** — demographics, allergy/medication strip, prescription/appointment/billing tabs; billing
  is read-only for doctors.
* **Walk-in** — name/phone/age/gender/complaint + next open slot; patient, placeholder account,
  accepted appointment and `WI-####` claim code are created in **one transaction**. Receipt shows the
  claim code and fee.
* **Prescribing** — live stock search with side effects; draft item editor; deterministic
  allergy/class, pairwise interaction, stock and expiry checks; hard block on allergy/contraindicated
  interaction or unavailable stock; a ≥20-character sealed override for allergy/contraindication
  conflicts. Save writes prescription + items + safety snapshot atomically and notifies the patient.
* **Reviews** — read-only doctor view of approved/pending/rejected reviews and rating distribution.
* **Settings** — verified credential summary, profile/fee/clinic/bio, password rotation with session
  revocation, theme/locale/timezone/date preferences.

## Safety & integrity

* Doctor route middleware requires both an approved user and a verified doctor profile.
* Request/chart/schedule endpoints check `doctor_id` ownership before acting; other doctors' records
  return `403`.
* Walk-in registration validates the slot against leave-aware availability and commits the
  placeholder user, patient chart, claim code and accepted appointment atomically.
* Prescription safety runs twice: the live panel and the save transaction. The saved
  `safety_snapshot` plus override justification and approver are immutable clinical evidence.
* Stock/expiry is checked at save; actual deduction remains a pharmacy dispense action.
* No hard delete of schedules/leaves; leave is cancelled by status, blocks are disabled.

## Verification

```bash
php artisan test --compact                         # 155 passed, 1,665 assertions
php artisan dusk --without-tty                     # 28 passed, 379 assertions
npm run typecheck && npm run build                 # clean
```

Lighthouse (in-app Chromium): **Accessibility 100**, Best Practices 100, SEO 100 on all nine doctor
screens in both themes. Dusk checks every doctor screen at **360 / 640 / 768 / 992 / 1440**;
screenshots are `tests/Browser/screenshots/part6-*`.

## Demo path

1. Sign in as `ayesha.rahman@medipath.test` / `MediPath#2026`.
2. Dashboard → open requests → accept a pending request → mark completed.
3. Schedule → add a block → add leave (overlapping bookings cancel with notices).
4. Patients → open a chart → review allergy strip and history.
5. Prescribe → choose an ENT-like/allergy patient → search Amlodipine → add → see safety panel →
   save; try Amoxicillin for a penicillin allergy to see the override gate.
6. Walk-in → register a patient on the next open slot → read the claim code.
7. Reviews → inspect distribution/status. Settings → change fee/bio, password/preferences.

## Deviations

1. **Leave cancels overlapping active appointments automatically** (prototype offered a
   notify-vs-auto-cancel choice). The audit + notification are explicit; there is no silent cancel.
2. **Schedule blocks support add and enable/disable**, not arbitrary inline editing; this keeps
   overlap validation and audit simple.
3. **Prescription stock is validated but not deducted at save** — deduction belongs to the pharmacy
   dispense transaction (Part 7).
4. **AI safety summary is deterministic fallback** at save; provider-backed safety text stays optional
   and never replaces the deterministic checks.
5. **Walk-in claim code is issued but not self-claimable from the doctor workspace**; the patient
   registration claim path remains the Part 1 flow.