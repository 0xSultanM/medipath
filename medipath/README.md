> **Looking for clone-and-run setup?** See the repository root [`README.md`](../README.md) for cross-platform local installation, Docker MySQL/Redis, demo accounts, tests and troubleshooting. This file is the detailed implementation log.

# MediPath

**AI-Powered Doctor Appointment and Medicine Safety System** — CSE-479 lab project.

Parts 0–9 are complete: foundation, guest/public journey, admin core and advanced operations, patient
portal, patient AI triage, doctor workspace, doctor prescribing with labelled AI summaries, pharmacist
portal, and final integration/security/accessibility/performance polish. The platform boots from a
seeded clinical database with four hardened role shells, a 17-component React design system in light
and dark themes, an end-to-end booking → prescribing → dispensing → billing journey, and an
append-only audit trail.

---

## Stack (PLAN §2)

| Layer | Implementation |
|---|---|
| Backend | **Laravel 12** monolith (PHP 8.4) — see *Deviations* for the Laravel 11 → 12 note |
| Frontend | **React 18 + TypeScript** via Inertia 2 (server-driven pages, no client-trust) |
| CSS | **Bootstrap 5.3** (Reboot + utilities + `--bs-*` bridge) + MediPath token/component Sass |
| Build | Vite 7 |
| Database | **MySQL 8.4** (CHECK constraints, unique active-slot key, FULLTEXT indexes) |
| Cache/sessions/queues | **Redis** (predis) — session, cache, queue, rate limiter |
| Real-time | **Laravel Reverb + Echo** (`NotificationCreated` on a private user channel) |
| Auth | Session guard + Sanctum package + role gates, login throttling |
| PDF / CSV | DomPDF + Maatwebsite Excel (wired for later phases) |
| Search | Scout + MySQL FULLTEXT indexes |
| Tests | **Pest 3** (feature/unit) + **Laravel Dusk** (browser, 360/768/1440) |

## Quick start

```bash
# 1. Services: MySQL 8 + Redis must be running (brew services start mysql@8.4 redis)
# 2. PHP dependencies
composer install

# 3. Environment
cp .env.example .env
php artisan key:generate

# 4. Database (MySQL: medipath for dev, medipath_test for Pest)
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS medipath CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS medipath_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate:fresh --seed

# 5. Frontend
npm install
npm run build        # or: npm run dev

# 6. Run
php artisan serve --host=127.0.0.1 --port=8000
# optional real-time + queues
php artisan reverb:start
php artisan queue:work redis

# 7. Browser tests (Chrome + chromedriver matching the installed Chrome)
cp .env .env.dusk.local   # Dusk database/session defaults are already tuned in the file
php artisan dusk:chrome-driver --detect
php artisan dusk --without-tty
```

Open <http://127.0.0.1:8000>.

> Requirements: PHP **8.4** with `pdo_mysql`, `intl`, `gd`, `zip`; Composer 2; Node 20+; MySQL 8; Redis 6+.

## Demo accounts

Shared password: **`MediPath#2026`** (configurable via `MEDIPATH_DEMO_PASSWORD`).

| Role | Email | Notes |
|---|---|---|
| Admin | `admin@medipath.test` | Full governance shell |
| Doctor (verified) | `ayesha.rahman@medipath.test` | Cardiology, 12 yrs, fee 1,200.00 |
| Doctor (verified) | `kamal.hossain@medipath.test` | Dermatology |
| Doctor (verified) | `nusrat.jahan@medipath.test` | Paediatrics |
| Doctor (verified) | `shirin.akter@medipath.test` | General Medicine |
| Doctor (pending) | `tanvir.alam@medipath.test` | In the admin approval queue |
| Pharmacist (approved) | `pharmacist@medipath.test` | Nadia Tabassum |
| Pharmacist (pending) | `sadia.islam@medipath.test` | In the admin approval queue |
| Patient | `patient@medipath.test` | Farhana Karim, hypertension, penicillin allergy |
| Patient | `rafiq.ahmed@medipath.test` | Type 2 diabetes |
| Patient | `shila.das@medipath.test` | Asthma, pending prescription in pharmacy |
| Patient | `imran.chowdhury@medipath.test` | Walk-in record `WI-4821` |
| Patient (child) | `ayaan.ahmed@medipath.test` | Paediatric asthma, peanut allergy |

> Money is never shown with a literal `$`. The runtime symbol is resolved from
> `system_settings.currency_code` by `App\Services\CurrencyFormatter` (BDT → ৳ on the seeded data).
> Dates use the configured timezone (`Asia/Dhaka`) and format (`d M Y`).

## What Parts 0–9 ship

### Database (PLAN §4 + operational tables)

All core tables are migrated with constraints, indexes and soft deletes: users, patients, doctors,
pharmacists, specialties, schedules, leaves, appointments, prescriptions/items, medicines,
inventory ledger, invoices/items, reviews, notifications, activity log, system settings, revenue
configs, emergency rules/contacts/banners, symptom→specialty map, FAQs, pending approvals,
allergies, patient medications, drug interactions, password resets, symptom-check logs and backup
snapshots.

Defence in depth:

* `CHECK (stock_quantity >= 0)`, `CHECK (rating BETWEEN 1 AND 5)`,
  `CHECK (revenue percentage BETWEEN 0 AND 100)`, enum-membership checks, etc.
* `UNIQUE(doctor_id, date, active_slot_key)` — one active booking per slot; terminal rows release it.
* `patients.user_id` is unique + restricted, and the model refuses re-linking or changing `patient_code`.
* `activity_log` and `inventory_ledger` are append-only at the model layer, with no update/delete routes.
* FULLTEXT indexes on medicines, FAQs, patients, invoices and doctor profiles.

### Services (PLAN Sprint 0.4)

* `SettingsService` — cached global settings, revenue percentage from effective-dated config, broadcast on change.
* `CurrencyFormatter` — the only money formatter; ICU-resolved symbol; parentheses for negatives.
* `ActivityLogService` — append-only audit writer.
* `NotificationService` — DB notification + Reverb broadcast; ownership-checked read endpoints.
* `ThemeProvider` — System → light/dark preference chain, persisted to `user_preferences`.
* Global JSON error shapes (`unauthenticated`, `too_many_attempts`, `immutable_record`) + client `ApiError` retry helper.

### Authentication & gates

* Session login/logout with rate limiting (5 attempts / minute, generic error copy).
* Role gates: admin, verified doctor (both user + profile), approved pharmacist, pending-only, self-action guard.
* Pending/suspended/archived accounts are blocked server-side; sessions regenerate on login.

### Layouts

* Guest shell: skip link, public navbar (emergency link scoped red), footer, toast/chatbot slots.
* Role shells: one sidebar DOM instance (fixed ≥992, focus-trapped drawer below 992), top bar with
  theme switch, notification bell (popover ≥768, drawer below 768), avatar and role badge from DB.

### Component library

See [`Docs/PART0_COMPONENTS.md`](Docs/PART0_COMPONENTS.md) for the full inventory, prototype
references and how each component demonstrates its four states.

### Phase 1 public journey

See [`Docs/PART1_SCREENS.md`](Docs/PART1_SCREENS.md) for route → prototype → SPEC mapping, state
behaviour, security choices and demo instructions. Highlights:

* `/` landing with specialty/date search, DB hotline strip, featured doctors.
* `/doctors` URL-synced filters, weekday availability, 1/2/3 grid, server pagination.
* `/doctors/{doctorCode}` verified-only profile, leave-aware 7-day table, approved reviews.
* `/emergency` one-tap tel cards, red-flag accordion, live banner, offline cache.
* `/login`, `/register` (atomic multi-role + private license upload), `/forgot-password`,
  `/reset-password/{token}` (hashed 30-minute single-use tokens + session revocation).
* `/pending` holding screen with 60-second status polling and rejection reason.
* `POST /api/chatbot` — deterministic red-flag intercept before FAQ; 20/min/IP.

**Demo walk-in claim code:** `WI-9001` (local only) merges the seeded placeholder history.

Routes:

* `/` — public landing; `/doctors`, `/doctors/{code}`, `/emergency`; `/login`, `/register`,
  `/forgot-password`, `/reset-password/{token}`; `/pending`
* `/dev/ui` — local-only component gallery with per-view state switchers (404 outside `local`)
* `/dev/foundation` — local-only foundation status + demo accounts
* `/dev/layouts/{admin|doctor|pharmacist|patient}` — local-only shell preview
* `/dev/frames/{360|640|768|1024|1440}?path=…` — local-only iframe harness for exact-width QA
* `/admin/dashboard`, `/doctor/dashboard`, `/pharmacist/dashboard`, `/patient/dashboard` — role shells

### Phase 2 admin core

See [`Docs/PART2_SCREENS.md`](Docs/PART2_SCREENS.md) for the full route/prototype map, security
decisions and demo path. Highlights:

* **Master dashboard** (`/admin/dashboard`) — users, awaiting action, appointments, revenue,
  configurable commission, prescription completion, live DB/queue/storage/AI health, attention
  queue and 12-week booked-vs-completed trend; `?range=7|30|90|365`.
* **User management** (`/admin/users`) — server-side search/role/status/idle filters, pager, create
  internal patient/admin accounts, contact edit, atomic role changes (reason required, old profile
  archived, sessions revoked), suspend/restore, typed-name archive.
* **Approvals & verification** (`/admin/approvals`) — pending sign-ups with private credential
  preview + rejection reason, and doctor verification decisions (approve, request resubmission,
  revoke) that gate booking access.
* **Doctors** (`/admin/doctors`) — create/edit profiles, verification drill-down, dependency-counted
  typed soft archive, restore.
* **Patients & portal** (`/admin/patients`) — staff-mode registration/edit, typed soft archive with
  counts, and a read-only as-patient portal tab (prescriptions, bills, allergies, active medicines)
  with every open audited.
* **Admin session hardening** — 15-minute server-side idle timeout (`admin.idle`) plus monotonic
  `users.session_version` revocation on suspend/archive/role change/password reset.

Routes added in Part 2:

* `/admin/dashboard`, `/admin/users`, `/admin/approvals`, `/admin/doctors`, `/admin/patients`
* `/admin/patients?tab=portal&portal={id}`, `/admin/patients/{patient}/portal`
* `/admin/approvals/{approval}/credential`, `/admin/doctors/{doctor}/credential` (private, admin-only)
* `POST` governance endpoints for user role/suspend/restore/archive, approvals, doctor verification
  and doctor/patient archive/restore.

### Phase 3 admin advanced

See [`Docs/PART3_SCREENS.md`](Docs/PART3_SCREENS.md) for the full route map, security decisions and
demo path. Highlights:

* **Global appointments** (`/admin/appointments`) — doctor/status/date filters, audit trail and
  reasoned cancel-on-behalf with slot release + notifications.
* **Clinical & billing records** (`/admin/records`) — prescriptions, invoices and live inventory;
  server-rendered prescription/invoice PDFs.
* **Reports & analytics** (`/admin/reports`) — seven sections, 30/90/365 ranges, CSV and PDF export.
* **Activity log** (`/admin/activity`) — append-only viewer with JSON detail, role/severity/date
  facets and server pager.
* **Knowledge & moderation** (`/admin/faq-reviews`) — FAQ CRUD/publish toggle and review
  approve/hide with moderator note + patient notification.
* **Safety console** (`/admin/safety`) — emergency contacts, deterministic red-flag rules and
  symptom→specialty mappings, all audited.
* **Global settings** (`/admin/settings`) — clinic identity, PDF headers, currency/locale/timezone,
  booking rules, AI flag and effective-dated revenue percentage with impact preview/history.
* **Data management** (`/admin/data`) — streamed CSV exports with formula-injection escaping,
  private metadata snapshots + restore drill, two-step CSV medicine import, password+typed danger
  zone.
* **Admin security** (`/admin/security`) — profile, configurable 5–120 minute idle timeout, password
  rotation and session revocation.

Routes added in Part 3:

* `/admin/appointments`, `/admin/records`, `/admin/reports`, `/admin/activity`,
  `/admin/faq-reviews`, `/admin/safety`, `/admin/settings`, `/admin/data`, `/admin/security`
* report CSV/PDF exports, prescription/invoice PDFs, FAQ/review/safety/settings CRUD,
  backup/verify/delete/import/danger endpoints, profile/password/revoke-session endpoints.

### Phase 4 patient portal

See [`Docs/PART4_SCREENS.md`](Docs/PART4_SCREENS.md) for the route map, security decisions, demo path
and deviations. Highlights:

* **Dashboard** (`/patient/dashboard`) — next visit with cancel/reschedule, server KPIs, quick actions,
  recent feed.
* **Find doctors + booking** (`/patient/doctors`, `/patient/book/{code}`) — verified directory with
  patient CTA, leave-aware date strip/slots, server-authoritative confirmation and the unique
  active-slot conflict path.
* **Appointments** (`/patient/appointments`) — status tabs/search/pager, contextual actions,
  reason-required cancel and reschedule with cutoff enforcement.
* **Prescriptions & bills** (`/patient/prescriptions`, `/patient/bills`) — owner-only histories,
  invoice/medicine search, server PDFs.
* **Safety profile** (`/patient/clinical-profile`) — allergies + medication CRUD, duplicate protection
  and audit trail.
* **Reviews** (`/patient/reviews`) — one review per completed visit, moderation status.
* **Notifications** (`/patient/notifications`) — all/unread feed, read state.
* **Assistant** (`/patient/symptom-checker`) — authenticated FAQ chatbot with deterministic red-flag
  escalation; AI Stage-2 is Phase 5.
* **Settings** (`/patient/settings`) — immutable IDs, chart details, password/session revocation,
  notification/appearance/locale preferences.

Routes added in Part 4:

* `/patient/dashboard`, `/patient/doctors`, `/patient/book/{doctorCode}`,
  `/patient/appointments` (+ reschedule/cancel), `/patient/prescriptions` (+ PDF),
  `/patient/bills` (+ PDF), `/patient/clinical-profile` (+ allergies/medications CRUD),
  `/patient/reviews`, `/patient/notifications`, `/patient/symptom-checker`, `/patient/settings`.

### Phase 5 patient AI features

See [`Docs/PART5_SCREENS.md`](Docs/PART5_SCREENS.md) for the pipeline, safety rules and demo path.

* **Stage 1 deterministic red-flag gate** — active `emergency_rules` match free-text symptoms before
  any AI call, halt triage, log the escalation and open an emergency alert dialog with DB hotlines.
* **Stage 2 specialist + guidance** — `symptom_specialty_map` scoring chooses a DB specialty;
  configured AI (strict JSON) may refine the summary/questions/specialty hint, which is
  cross-validated against active specialties. Provider failure/timeout falls back to a clearly
  labelled deterministic answer.
* **Patient surface** — `/patient/symptom-checker` with common-symptom chips, duration/onset/severity
  intake, consent, four-state result view, urgency badge, doctor questions, verified-doctor booking
  links and emergency modal.
* **Assistant** — the authenticated FAQ chatbot remains available via the widget; emergency paths
  never depend on AI.

Routes added in Part 5:

* `POST /patient/symptom-check` (patient-only, throttled, no-store) returns the Stage-1 emergency
  or Stage-2 guidance payload.

### Phase 6 doctor portal

See [`Docs/PART6_SCREENS.md`](Docs/PART6_SCREENS.md) for the complete route/safety map.

* **Dashboard** (`/doctor/dashboard`) — today's KPIs, timeline, pending queue, leave warning.
* **Schedule & leave** (`/doctor/schedule`) — weekday blocks with overlap validation, enable/disable,
  approved leave that hides slots and atomically cancels overlapping appointments with notices.
* **Requests** (`/doctor/requests`) — pending/today/upcoming/completed/cancelled, accept, reject with
  reason, mark completed, cancel with notice; every transition audited + patient-notified.
* **Patients** (`/doctor/patients`) — only patients this doctor treated; search, last-visit, allergy
  filters; read-only chart with allergy strip and prescription/appointment/billing tabs.
* **Walk-in** (`/doctor/walk-in`) — patient + placeholder account + accepted appointment + `WI-####`
  claim code in one transaction; receipt shown.
* **Prescribe** (`/doctor/prescriptions/new`) — live stock/side-effect search, deterministic allergy
  class matching, pairwise interactions, stock/expiry checks, sealed override, atomic save, PDF.
* **My reviews** (`/doctor/reviews`) — authenticated read-only reviews and moderation state.
* **Settings** (`/doctor/settings`) — profile/fees/bio/clinic, password rotation, preferences.

Routes added in Part 6:

* `/doctor/dashboard`, `/doctor/schedule` (+ blocks/leaves), `/doctor/requests` (+ decisions),
  `/doctor/patients` (+ chart), `/doctor/walk-in`, `/doctor/prescriptions/new` (+ safety/save/PDF),
  `/doctor/medicines/search`, `/doctor/reviews`, `/doctor/settings` (+ profile/password/preferences).

### Phase 7 doctor prescribing & AI

See [`Docs/PART7_SCREENS.md`](Docs/PART7_SCREENS.md) for the route/provenance map.

* **Prescription history** (`/doctor/prescriptions`) — doctor-scoped search by code/patient, status
  filter, pagination, sealed PDF download and chart jump; overrides flagged per row.
* **AI safety summary** (`POST /doctor/prescriptions/ai-review`) — opt-in, runs after the
  deterministic checks; provider-backed strict JSON when configured, otherwise a labelled
  **deterministic fallback**. Advisory only: it can never clear an allergy/stock block.
* **Save provenance** — `ai_status` + `ai_safety_summary` are stored atomically with items, notes,
  safety snapshot and override seal; editing an item clears the panel so stale text cannot be saved.
* **Doctor notifications** (`/doctor/notifications`) — all/unread tabs, counts, pagination, action
  links and read state through the shared ownership-checked endpoints.
* **Role-aware bell** — "View all" points at the active role's notification page.

### Phase 8 pharmacist portal

See [`Docs/PART8_SCREENS.md`](Docs/PART8_SCREENS.md) for the complete route/integrity map.

* **Dashboard** (`/pharmacist/dashboard`) — pending queue with age and stock state, low/out-of-stock
  counts, today's billed total and dispense count, urgent stock/expiry queue.
* **Pending queue & safety gate** (`/pharmacist/prescriptions`) — deterministic checks re-run at the
  counter; sealed overrides displayed; dispense locked until pharmacist verification; refusal with
  reason notifies both doctor and patient.
* **Dispense & bill** (`/pharmacist/prescriptions/{id}/dispense`) — full prescribed quantities,
  server-calculated live totals (no client money formatting), consultation fee, fixed/percent
  discount, payment method and immediate-paid option.
* **Atomic engine** — one transaction locks the prescription and medicine rows, deducts stock, writes
  append-only ledger entries, issues the invoice with a currency snapshot, closes the prescription,
  audits and notifies. Any failure rolls back.
* **Inventory** (`/pharmacist/inventory`) — add/edit, opening stock, restock with batch/expiry/price,
  soft archive and the recent ledger.
* **Alerts** (`/pharmacist/alerts`) — stock, expired, critical ≤30d and upcoming 31–90d tiers.
* **Invoices** (`/pharmacist/invoices`) — search/filter, paid/refund transitions with a required
  refund reason, PDF download.
* **Workflow** (`/pharmacist/workflow`) — derived six-stage journey with blocked safety state.
* **Notifications & settings** — shared read-state feed and approved-credential profile settings.

### Phase 9 integration & polish

See [`Docs/PART9_ACCEPTANCE.md`](Docs/PART9_ACCEPTANCE.md) for the final acceptance matrix.

* **End-to-end journey** — registration → booking → doctor accept/complete → prescribing → pharmacy
  gate → atomic dispense → invoice payment → patient PDFs → discharged workflow, with the full audit
  trail asserted in one test.
* **Security audit** — 4×4 portal role matrix, IDOR on patient/doctor PDFs, SQL-injection payloads,
  stored XSS escaping, CSRF token sharing, password/session revocation and mass-assignment guards.
* **Responsive** — all portal screens at 360/640/768/992/1440 plus a cross-portal 1024 sidebar check.
* **WCAG** — Lighthouse accessibility 100 in both themes, keyboard skip link, modal focus/Escape,
  reduced motion; theme-transition and empty-star contrast defects fixed.
* **Performance** — composite indexes for queue/invoice/alert queries plus query-count guards proving
  list pages stay flat as rows grow.

### Four states on every data view

Every data-bound view is wrapped in `DataView` and accepts `State=success|skeleton|empty|error`:

* Interactive QA: use the per-section switcher in `/dev/ui`.
* URL-driven QA: append `?state=skeleton`, `?state=empty` or `?state=error` to any page
  (the shared `ui.initialState` becomes the default for all data views).
* Ready/Retry keep the current filters and show the last-known timestamp.

## Tests

```bash
# Feature/unit suite (MySQL medipath_test) — 185 tests / 2,247 assertions
php artisan test --testsuite=Feature

# TypeScript + production bundle
npm run typecheck
npm run build

# Browser acceptance (Chrome + chromedriver; app must be seeded and served on :8000)
# 38 tests / 482 assertions at 360/640/768/992/1024/1440
php artisan dusk --without-tty
```

Phase 0–3 coverage: role gates (all four roles + abuse), brute-force throttling, pending/
rejected/suspended handling, notification ownership, append-only models, DB constraints,
active-slot race, currency contract, settings cache/broadcast, seed graph idempotency, dev-route
guard, token audit, emergency-scope audit; guest directory/profile/emergency tests; atomic
registration + walk-in claim + private upload; password reset token lifecycle/session revocation;
chatbot red-flag precedence + FAQ + rate limiting; admin idle timeout, final-admin/self guards,
atomic role changes + session revocation, approval decisions, private credential previews, doctor
verification decisions, doctor/patient CRUD + typed soft archive, portal audit; appointment
cancellation, report PDF/CSV, FAQ lifecycle, review moderation, safety CRUD, effective-dated
revenue, data export/backup/import, admin profile/password/session revocation; pharmacy atomic
dispense + rollback, partial-dispense rejection, refusal, ledger integrity, inventory lifecycle,
alert tiers, invoice transitions and derived workflow; final cross-portal journey, role/IDOR/injection
audit, index assertions and queue/invoice query-count guards. Dusk checks run at
**360 / 640 / 768 / 992 / 1440** with screenshots in `tests/Browser/screenshots/`.

The gallery, every Phase 1 page, all 15 Phase 2/3 admin screens, all 11 patient portal screens, all
11 doctor workspace screens and all 10 pharmacy screens pass Lighthouse **Accessibility 100**, Best
Practices 100 and SEO 100 in both themes, including the doctor builder with the AI panel open and both
dispense states; the final Part 9 re-audit of guest, admin, doctor, pharmacist and patient key screens
also passed at 100 in both themes;
status is conveyed by dot + text + icon, and every focus target is ≥44 px.

## Project structure (PLAN §6)

```
app/
  Enums/                 role, status, severity enums (labels/tones)
  Events/                NotificationCreated, SettingsChanged
  Exceptions/            ImmutableRecordException
  Http/Controllers/      Auth, Admin, Guest, Foundation, RoleHome, Notification, Preference, Dev
  Http/Middleware/       RoleGate, VerifiedDoctor, ApprovedPharmacist, AdminIdleTimeout,
                         PendingStatusOnly, PreventSelfAction, EnsureSessionFresh,
                         HandleInertiaRequests
  Models/                full domain model set
  Services/              SettingsService, CurrencyFormatter, ActivityLogService,
                         NotificationService, Admin User/Doctor/Patient/Approval/
                         Appointment/Settings/Report/Data services,
                         PatientBookingService, SymptomTriageService,
                         DoctorAppointment/Schedule/Walk-In/PrescriptionSafety services,
                         MedicationSafetyAiService, PharmacyDispense/Inventory/Invoice services
  Support/               ActivityActions
database/
  migrations/            schema + CHECKs + FULLTEXT + Sanctum
  seeders/               specialties, medicines, interactions, safety rules,
                         symptom maps, FAQs, settings, demo users + clinical graph
resources/
  js/
    Components/          Navigation, Data, Feedback, Admin, Safety, AI, Overlay, Workflow, ui
    Layouts/             GuestLayout, RoleLayout
    Pages/               Admin (Parts 2–3), Patient (Parts 4–5), Doctor (Parts 6–7),
                         Pharmacist (Part 8),
                         Guest, Foundation, Role, Dev
    providers/           ThemeProvider, ToastProvider
    hooks/, lib/, types/
  sass/                  _tokens.scss, _bootstrap-map.scss, base/layout + components/*
  views/app.blade.php    Inertia root (theme applied before hydration)
routes/                  web.php, channels.php
tests/                   Pest feature + Dusk browser
```

## Design system source

The UI is governed by `design-out/`:

* `tokens.json` / `tokens.scss` → ported to `resources/sass/_tokens.scss` (light + dark mode maps,
  CSS custom properties, focus ring, touch target, `--mp-*`).
* `COMPONENT-SPEC.md` → each React component header cites its § and its prototype reference.
* `prototypes/**/*.html` → visual acceptance target (checked with the exact-width frame harness and Dusk screenshots).
* `DESIGN-RATIONALE.md` → AA contrast pairs, emergency-red scope ledger, edge-case decisions.

Emergency red (`#B91C1C`) is confined to emergency surfaces: the emergency page kit, chatbot
in-stream alert, escalation dialog, red-flag badges, hotline CTAs and the navbar emergency link.
Routine destructive actions use danger `#DC2626`. This is enforced by
`tests/Feature/DesignContractTest.php`.

## Deviations from PLAN (documented, Parts 0–9)

1. **Laravel 12 instead of Laravel 11.** Laravel 11 releases are blocked by Composer's security
   advisory policy (unpatched advisories in the whole 11.x line). Laravel 12 is API-compatible with
   the PLAN's architecture (same schema, services, middleware, Blade/Inertia, Vite).
2. **Inertia 2 as the React bridge.** The PLAN allows a standalone React frontend; Inertia gives
   server-driven props/pagination/validation without a second API surface, while keeping React 18.
3. **PHP 8.4.** PHP 8.6 (the machine default at scaffold time) emitted framework deprecations;
   PHP 8.4 is the supported Laravel 12 runtime.
4. **`--mp-on-primary` / `--mp-on-emergency` additive tokens.** Solid-fill button text must remain
   white in both themes (per DESIGN-RATIONALE §2); the existing palette is unchanged.
5. **Currency symbol lookup uses a native ICU locale map** (e.g. BDT → `bn_BD`) so ৳ is resolved
   from intl rather than hardcoded in any UI file.
6. **Phase 1 chatbot is FAQ + deterministic rules only.** AI retrieval/analysis is introduced behind
   the same service contract in Phases 5 and 7; the emergency path never depends on it.
7. **Booking/review submission remain Phase 4.** The public profile has the guest booking gate and
   the patient-portal destination is the Phase 4 entry point.
8. **`users.session_version` auth generation.** Redis sessions are not enumerable without a per-user
   index, so Part 2 adds a monotonic counter used by `EnsureSessionFresh`; deletion of the `sessions`
   table rows is best-effort for database-driver sessions. This revokes suspend/archive/role-change
   sessions deterministically.
9. **Seeded credential previews render a generated demo page.** Demo approval rows reference
   placeholder paths. The admin-only preview route serves a real upload inline when one exists;
   otherwise it renders an escaped, watermarked, script-free “demo preview” so the private-preview
   workflow is demonstrable without shipping fake PDFs.
10. **Dense admin tables convert to cards between 640–1199px.** COMPONENT-SPEC §4 mandates card
    conversion below 640; admin tables with five-plus columns opt into one earlier conversion
    (`tbl-cards-md`) so 640/768/992 breakpoints remain readable. ≥1200px uses the desktop table.
11. **Backups are metadata snapshots.** Production would use a physical SQL dump; this project
    writes private metadata JSON so backup/verify/delete workflows are demonstrable without
    exporting password hashes.
12. **Demo reseed is local/testing only** and re-runs the idempotent seeders; it does not truncate
    clinical history.
13. **Redis sessions are not enumerable.** The security screen lists DB-backed rows and always shows
    the current session; “revoke others” is authoritative via the `session_version` bump.
14. **Admin security is included in Part 3** (Sprint 2.1 profile/security) so the admin navigation
    has no dead link.
15. **Patient AI Stage-2 is deferred to Phase 5.** Part 4 ships the authenticated FAQ/rule-first
    assistant; red flags still escalate deterministically.
16. **Booking picker shows server open slots only**; booked/past variants are represented by the
    shared component contract and the conflict response rather than fabricated slot rows.
17. **Email/SMS notification preferences are stored, not transported.** The in-app feed is active;
    transport providers are a later integration.
18. **No production AI key is committed.** Stage-2 OpenAI support is implemented with strict JSON,
    timeout and cross-validation, but the demo intentionally runs the deterministic fallback unless
    `OPENAI_API_KEY` plus `ai_enabled`/`ai_model` are configured. Red flags never wait for AI.
19. **The AI specialty hint is advisory only.** It must match an active database specialty; otherwise
    the `symptom_specialty_map` result (or General Medicine) is used.
20. **Doctor leave auto-cancels overlapping active appointments** with an explicit reason and patient
    notification (the prototype offered a notify-vs-cancel choice).
21. **Schedule blocks support add + enable/disable**, not arbitrary inline edit.
22. **Prescription stock is validated but not deducted at save**; deduction belongs to the pharmacy
    dispense transaction in Part 8.
23. **Walk-in claim codes are issued by the doctor** but self-claim remains the Part 1 registration
    path.
24. **Prescription AI is opt-in and advisory.** The demo runs a labelled deterministic fallback unless
    a provider key is configured; the AI can never clear a deterministic blocker.
25. **Prescription history is read-only.** Corrections are a new prescription plus the pharmacy
    cancellation workflow, preserving the append-only trail.
26. **Notification read state uses the shared API** rather than doctor-specific endpoints, so every
    role's bell and page agree.
27. **Workflow status is derived** from the latest prescription/invoice and current stock instead of a
    mutable `patients.workflow_status` column; a single patient column cannot represent per-visit
    progress truthfully.
28. **Partial dispense is rejected.** The prototype shows editable quantities; the schema has no
    partial-dispense state, so the full prescribed quantity is required or the prescription is refused.
29. **Expired batches are replaced through restock**, not quarantined by a destructive action; the
    ledger keeps the full trail.
30. **Invoice lines use the prescription price snapshot**, protecting historical bills from later
    catalogue changes.
31. **Payment-state changes never move stock.** A refund is a billing action; stock reversal remains a
    separate explicit ledger movement.
32. **AI safety text is re-displayed with provenance** on the gate and can never clear a deterministic
    blocker.

## Project status

The planned Parts 0–9 are complete and the acceptance suite is green. Remaining work is deployment
and product expansion rather than scheduled coursework: email/SMS transport, queue workers and a
scheduler for nightly stock sweeps, production object storage/backups, and a future partial-dispense
schema if the clinic needs it.
