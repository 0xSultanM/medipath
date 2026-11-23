# Part 3 — Admin Advanced (complete)

Visual acceptance: `design-out/prototypes/admin/06-appointments.html` … `14-security.html`.
All screens inherit the Admin shell, the four-state data contract, light/dark tokens and
responsive card/table behaviour from Parts 0–2.

## Route map

| Screen | Prototype | Route(s) | Controller |
|---|---|---|---|
| Global appointments | `admin/06` | `GET /admin/appointments`, `POST /admin/appointments/{appointment}/cancel` | `Admin\AppointmentController` |
| Clinical, billing & inventory records | `admin/07` | `GET /admin/records?tab=prescriptions\|invoices\|inventory` | `Admin\RecordController` |
| Prescription PDF | `admin/07` | `GET /admin/records/prescriptions/{prescription}/pdf` | `Admin\RecordController@prescriptionPdf` |
| Invoice PDF | `admin/07` | `GET /admin/records/invoices/{invoice}/pdf` | `Admin\RecordController@invoicePdf` |
| Reports & analytics | `admin/08` | `GET /admin/reports?range=30\|90\|365` | `Admin\ReportController` |
| Report exports | `admin/08` | `GET /admin/reports/export/csv`, `…/pdf` | `Admin\ReportController` |
| Activity log | `admin/09` | `GET /admin/activity` (search/role/severity/date/pager) | `Admin\ActivityController` |
| FAQ manager | `admin/10` | `GET /admin/faq-reviews`, `POST/PUT/DELETE/POST toggle /admin/faqs…` | `Admin\KnowledgeController` |
| Review moderation | `admin/10` | `POST /admin/reviews/{review}/moderate` | `Admin\KnowledgeController` |
| Safety console | `admin/11` | `GET /admin/safety`, contacts/rules/mappings CRUD + toggles | `Admin\SafetyController` |
| Global settings + revenue | `admin/12` | `GET/PUT /admin/settings` | `Admin\SettingsController` |
| Data management | `admin/13` | `GET /admin/data`, exports, backups, import, danger | `Admin\DataController` |
| Admin security | `admin/14` | `GET /admin/security`, profile/password/revoke routes | `Admin\SecurityController` |

## What ships

* **Appointments** — server-side filters (search, doctor, status, date), pager, details, audit
  deep-link, and **cancel on behalf** with a required reason. Cancellation releases the unique
  active slot, records actor + reason, notifies patient and doctor, and writes an immutable log row.
* **Records** — read-only prescription, invoice and live inventory tabs. Prescription safety shows
  `clean` or sealed override; PDFs are rendered server-side from the shared DomPDF templates.
  Historic invoice money uses its currency snapshot.
* **Reports** — seven sections: monthly revenue, patient growth, prescription completion, invoice
  collection + aging, stock/expiry risk, appointment conversion/cancellation, and top-doctor
  leaderboard. CSV export neutralises formula injection; PDF export uses the same dataset. Range
  chips are URL-synced.
* **Activity log** — append-only viewer with JSON detail modal, actor-role/severity/date filters,
  server pager. Severity is derived: emergency events are the only `critical` badges.
* **Knowledge & moderation** — FAQ create/edit/publish-toggle/delete with reindex copy; reviews are
  approved or hidden with a moderator note, reviewer id + timestamp, and patient notification.
  Ratings are never destroyed.
* **Safety console** — emergency contacts CRUD/toggle, deterministic red-flag rule editing/toggle,
  symptom→specialty mapping CRUD/toggle. Every edit writes `safety.config_updated`.
* **Settings** — clinic identity, PDF header/footer, currency/locale/timezone/date format, booking
  cutoff, patient auto-approve, AI flag/model and effective-dated revenue percentage with preview.
  Settings are cached + broadcast; future revenue rows never re-price history.
* **Data management** — streamed CSV exports (patients/doctors/invoices/stock ledger), private
  metadata backup snapshots + restore drill, two-step CSV medicine import (dry-run → transactional
  apply), and password + typed-confirm danger actions (purge archived sessions, delete failed
  backups, local demo reseed).
* **Security** — admin profile + idle-window setting (5–120 min), password rotation with session
  revocation, Redis-aware session list and “revoke all other sessions”.

## Integrity & safety

* Cancellation, backups, import, exports, settings, revenue, FAQ, review, safety and security
  actions all write to the append-only `activity_log`; there are no update/delete routes for log
  rows.
* CSV exports escape leading `= + - @`, tab and CR (formula injection).
* Import writes only after an explicit dry-run token; apply is transactional.
* Backup snapshots are metadata JSON on the private `backups` disk; the restore drill verifies file
  presence and marks the snapshot `verified`/`failed`. They intentionally do not dump user
  password hashes.
* Danger actions require the current admin password **and** a typed phrase (`PURGE`, `DELETE`,
  `RESET`); demo reseed is local/testing only.
* Session revocation bumps `users.session_version`; Redis sessions are not enumerable, so the
  version marker is the source of truth and DB session rows are removed where present.

## Verification

```bash
php artisan test --compact                         # 131 passed, 1,285 assertions
php artisan dusk --without-tty                     # 19 passed, 243 assertions
npm run typecheck && npm run build                 # clean
```

Lighthouse (in-app Chromium) on all Part 2 + Part 3 admin screens, including the as-patient preview:
**Accessibility 100**, Best Practices 100, SEO 100 in **both themes** after fixing the shared tabs
counter contrast and the sidebar profile link accessible name. Dusk checks all Part 3 screens at
**360 / 640 / 768 / 992 / 1440** with screenshots in `tests/Browser/screenshots/part3-*`.

## Demo path

1. `/admin/appointments` → filter by status/doctor → cancel a pending appointment on behalf; inspect
   `/admin/activity?q=APT-3009`.
2. `/admin/records` → prescriptions → invoice PDF → inventory; try the four-state switch.
3. `/admin/reports` → 30d/90d/Year → download CSV and PDF.
4. `/admin/faq-reviews` → edit/publish an FAQ, then moderate the pending review with a note.
5. `/admin/safety` → toggle a red-flag rule, edit a contact, add a symptom mapping.
6. `/admin/settings` → change the revenue percentage with a future effective date; see history.
7. `/admin/data` → export patients, create + restore-drill a snapshot, dry-run a medicine CSV.
8. `/admin/security` → update profile/idle window, rotate password, revoke other sessions.

## Deviations

1. **Backup snapshots are metadata snapshots.** A real production deployment would stream a
   `mysqldump`/physical backup; this project stores private metadata JSON (table counts + timestamp)
   so the UI, audit, verify and retention workflows are demonstrable without exporting credentials.
2. **Demo reseed is local/testing only.** It re-runs the idempotent seeders; clinical history is not
   truncated.
3. **Redis sessions are not enumerable.** The security screen lists DB-backed session rows and
   always shows the current session; “revoke others” is authoritative through the version bump.
4. **Admin security screen is included here** (Sprint 2.1’s profile/security surface) so the admin
   navigation has no dead link. It uses the Part 2 session-version contract.