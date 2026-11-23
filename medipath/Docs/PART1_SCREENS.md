# Part 1 — Guest & Public Screens

Every page uses the shared guest shell (§1), design tokens, four-state contract (§0/§17) and
the global emergency-red scope ledger.

| Route | Screen / prototype | SPEC sections | States & behaviors |
|---|---|---|---|
| `/` | Landing (`guest/01-landing.html`) | §1 navbar, §3 KPI rhythm, §7 doctor cards, §11 chatbot, §12 emergency strip | featured-doctor data view (skeleton/empty/error/success); specialty+date hero search; emergency hotline from DB |
| `/doctors` | Directory (`guest/02-directory.html`) | §5 filters, §7 cards, §4 pager, §17 states | URL-synced search/specialty/rating/today/weekday/date/sort/page; 1/2/3 column grid; <768 filter drawer; four states |
| `/doctors/{code}` | Doctor profile (`guest/03-doctor-profile.html`) | §7 header, §8 availability, §9 reviews/distribution | verified-only 404; 7-day leave-aware table ×4; approved-only reviews + pagination; guest booking gate |
| `/emergency` | Emergency info (`guest/04-emergency.html`) | §12 scoped red kit | 6 tel: call cards, red-flag accordion by category, live time-bounded banner, empty/error safe defaults, offline note, service-worker cache |
| `/login` | Login (`guest/05-login.html`) | §15 inline errors/spinner, §1 shell | password reveal, remember, generic credential errors, throttle feedback, safe post-login redirect, local demo fill |
| `/register` | Register (`guest/06-register.html`) | §5 role picker, §15 validation | patient/doctor/pharmacist branches; DB specialties/authorities; private credential upload; walk-in claim merge; atomic tx + queued notification |
| `/forgot-password`, `/reset-password/{token}` | Forgot/reset (`guest/07-forgot-reset.html`) | §14 3-stage stepper, §15 feedback | generic success, 30-min hashed token, single-use, expiry error, session revocation, local demo link |
| `/pending` | Pending/rejected holding (`guest/08-pending.html`) | §14 Stepper3, §3 queue progress, §15 retry | 60s poll `/pending/status`, 4 states, approval/rejection panel, sign out |

## Server-side behavior

- **Doctor availability**: `DoctorAvailabilityService` computes schedules − breaks − approved leave
  − pending/accepted bookings − past slots. Used by directory “available today”, weekday filter,
  profile table and booking (Phase 4).
- **Registration**: `RegistrationService` runs one transaction for user + profile + credential path
  + approval item + activity log, then dispatches `RegistrationReceived` on Redis. Patient
  auto-approval follows `system_settings.patient_auto_approve`; doctors/pharmacists always wait for
  admin approval.
- **Walk-in claim**: only placeholder `@walkin.medipath.local` records can be claimed. History is
  moved to the new patient and the placeholder archived; an active account’s code is rejected.
- **Password reset**: SHA-256 token hashes only, 30-minute TTL, consumed once; `password_changed_at`
  invalidates Redis/file/database sessions through `EnsureSessionFresh`.
- **Chatbot**: `POST /api/chatbot` (20/min/IP) runs `EmergencyRuleEngine` **before** FAQ retrieval.
  A red flag writes a symptom-check log + audit entry and returns emergency contacts; otherwise the
  best published FAQ is returned, or a deterministic fallback with suggestions.
- **Emergency offline**: `/sw.js` is registered only from `/emergency`, caching the document and
  build assets after the first load.

## Demo walk-in code

`WI-9001` (local only). The seeded placeholder has one past appointment and a mild penicillin
allergy; registering with this code merges them into the new patient account.

## Verification

```bash
php artisan test --testsuite=Feature   # 106 tests, 985 assertions
php artisan dusk --without-tty         # 9 browser tests at 360/768/1440
```

Screenshots: `tests/Browser/screenshots/part1-*.png`.

Lighthouse accessibility is **100** in light and dark themes on `/`, `/doctors`, the doctor
profile and `/emergency`; guest auth screens also pass AA. All guest pages avoid literal currency
symbols (money comes from `CurrencyFormatter`) and expose role/specialty labels from the DB.