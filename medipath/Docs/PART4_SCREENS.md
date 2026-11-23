# Part 4 — Patient Portal (complete)

Visual acceptance: `design-out/prototypes/patient/01-dashboard.html` … `08-profile-settings.html`.
All pages use the patient role shell, server-side data and the four-state
`DataView` contract, with the same 360/640/768/992/1440 checks as Parts 2–3.

## Route map

| Screen | Prototype | Route | Controller |
|---|---|---|---|
| Dashboard | `patient/01` | `/patient/dashboard` | `Patient\DashboardController` |
| Find doctors | `guest/02` (patient CTA) | `/patient/doctors` | `Patient\DoctorBrowseController` |
| Booking / reschedule | `patient/02` | `/patient/book/{doctorCode}`, `POST /patient/appointments`, `POST …/{appointment}/reschedule` | `Patient\AppointmentController` |
| Appointment history | `patient/03` | `/patient/appointments` (status tabs/search/pager), `POST …/{appointment}/cancel` | `Patient\AppointmentController` |
| Prescriptions + PDF | `patient/05` | `/patient/prescriptions`, `/patient/prescriptions/{prescription}/pdf` | `Patient\PrescriptionController` |
| Bills + PDF | `patient/06` | `/patient/bills`, `/patient/bills/{invoice}/pdf` | `Patient\InvoiceController` |
| Allergies & medicines | `patient/07` | `/patient/clinical-profile` + allergy/medication CRUD | `Patient\ClinicalProfileController` |
| Reviews | `patient/03` | `/patient/reviews`, `POST /patient/reviews` | `Patient\ReviewController` |
| Notifications | `patient/01` drawer | `/patient/notifications` + existing read endpoints | `Patient\NotificationController` |
| Assistant | `patient/04` | `/patient/symptom-checker` | `Patient\SymptomCheckerController` |
| Profile & settings | `patient/08` | `/patient/settings` + profile/password/preferences/revoke | `Patient\SettingsController` |

## What ships

* **Dashboard** — next pending/accepted visit with room/clinic/fee and cancel/reschedule;
  server KPIs (upcoming visits, prescriptions, outstanding bills, symptom checks); quick actions and
  recent notification feed.
* **Directory + booking** — verified doctors with availability context, direct patient booking links,
  leave-aware date strip and slots, server-authoritative slot validation, reason capture, and the
  unique `(doctor, date, active_slot_key)` conflict path.
* **Appointment history** — status tabs (all/pending/accepted/completed/cancelled), search, server
  pager, contextual actions (cancel/reschedule, view prescription, leave review, rebook) and reason
  display. Accepted cancellations/reschedules respect the clinic cutoff; pending requests can be
  withdrawn.
* **Prescriptions** — expandable owner-only cards with medicine items, notes, safety pass/override,
  invoice link and server-rendered PDF.
* **Bills** — outstanding/paid/refunded KPIs, invoice-code and medicine search, status filters,
  pagination, PDF download; all queries scoped by `patient_id`.
* **Allergies & medications** — tabbed safety lists, add/edit allergy, duplicate-substance rejection,
  add/edit/stop/reactivate medication, audit history. Severity labels/badges use the DB enum map.
* **Reviews** — one review per completed visit, 1–5 rating, optional comment, pending moderation,
  published/rejected status and public moderation notes.
* **Notifications** — server-owned feed, all/unread tabs, mark read/all read, action links.
* **Assistant** — authenticated FAQ chatbot, scoped with the deterministic red-flag interceptor;
  AI Stage-2 is deferred to Phase 5.
* **Settings** — immutable patient ID/walk-in code, personal chart details, password rotation with
  session revocation, notification preferences, theme/language/timezone/date format and reduce-motion.

## Security & integrity

* Owner-only controllers resolve the patient from `auth()->user()->patient`; PDFs additionally compare
  `patient_id` and return 403 for other accounts.
* Booking re-checks availability server-side and catches the DB unique violation as a user-facing
  “slot just taken” error; no client-provided availability is trusted.
* Cancellation/reschedule require ownership, an active status and (for accepted visits) the clinic
  cutoff. Every action writes an immutable activity entry and notifies the doctor.
* Allergy duplicates are rejected inside the patient scope; medication changes are audited.
* Review eligibility is server-checked: owned completed appointment, no existing review.
* Password/session changes bump `users.session_version`, re-stamp the current session, delete other
  DB session rows where present and log the event.
* The assistant page keeps the pinned disclaimer and routes red flags to emergency contacts before
  any FAQ answer.

## Verification

```bash
php artisan test --compact                         # 140 passed, 1,466 assertions
php artisan dusk --without-tty                     # 23 passed, 313 assertions
npm run typecheck && npm run build                 # clean
```

Lighthouse (in-app Chromium): **Accessibility 100**, Best Practices 100, SEO 100 on all 11 patient
screens in both themes; the full admin (15) + patient (11) set is clean. Dusk covers all patient
screens at 360/640/768/992/1440; screenshots are `tests/Browser/screenshots/part4-*`.

## Demo path

Sign in as `patient@medipath.test` / `MediPath#2026`.

1. Dashboard → cancel/reschedule CTA → KPIs/state switch.
2. Find Doctors → Book → choose a leave-aware date/slot → send request.
3. My Appointments → status tabs → reschedule/cancel → review a completed visit.
4. My Prescriptions → expand rows → PDF. My Bills → search invoice/medicine → PDF.
5. Allergies & Medicines → add allergy (try a duplicate), add/stop a medication.
6. Notifications → mark read/all read. Assistant → ask a FAQ; red-flag phrases still escalate.
7. Settings → edit chart details, rotate password, change preferences and revoke other sessions.

## Deviations

1. **AI Stage-2 is deferred to Phase 5.** This part ships the authenticated FAQ/rule-first assistant,
   not the symptom-to-specialty AI pipeline.
2. **The booking picker renders open slots only** (plus closed/leave dates). Booked/past variants are
   handled by the shared `SlotPicker` contract and the server conflict path, not fabricated rows.
3. **Notifications use the existing in-app feed.** Email/SMS preference toggles are stored, while
   actual transport providers remain a later integration.
4. **No walk-in self-claim in this part.** Patient registration/linking from Part 1 remains the
   source for new chart links; the portal displays the immutable patient and walk-in codes.