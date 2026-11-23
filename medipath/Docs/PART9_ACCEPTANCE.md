# Part 9 — Integration Testing & Polish (complete)

Final acceptance for the Parts 0–8 platform: one real cross-portal journey, a security audit,
responsive and keyboard checks, WCAG AA re-audit, performance indexes and the closing documentation.

## 1. End-to-end journey

`tests/Feature/EndToEndJourneyTest.php` walks the complete product without shortcuts:

1. **Registration** — patient self-registers (`/register`), lands in the portal, patient chart exists.
2. **Booking** — doctor schedule seeded; patient books `09:00` (`POST /patient/appointments`) → pending,
   doctor notified.
3. **Doctor decision** — doctor accepts and completes the request.
4. **Prescribing** — doctor saves a prescription with clinical/pharmacy/patient notes and the price
   snapshot.
5. **Pharmacy gate** — pharmacist loads the gate; deterministic safety is clear and `canDispense` is true.
6. **Atomic dispense** — pharmacist dispenses 14 units with a 5% discount and consultation fee.
   One transaction deducts stock, writes the ledger, issues the invoice (2 lines), closes the
   prescription and notifies the patient.
7. **Payment** — pharmacist marks the invoice paid; patient is notified.
8. **Patient view** — prescriptions and bills show the correct records; both PDFs download.
9. **Discharge** — pharmacist workflow shows all six stages done and the paid invoice.
10. **Audit trail** — login, booking, accept, complete, prescribe, dispense, invoice and payment actions
    are all present in the append-only log.

The test asserts **124 assertions** in one run.

## 2. Security audit

`tests/Feature/SecurityAuditTest.php` covers the PLAN Sprint 9.2 checklist:

| Check | Result |
|---|---|
| Portal role matrix (4 roles × 4 portals) | only the matching portal returns `200`; others `403` |
| Cross-patient prescription / bill PDFs | `403` |
| Cross-doctor prescription PDF | `403`; owner gets `200` |
| Unapproved doctor / missing pharmacist profile | `403` |
| SQL injection payloads in search | treated as literal text; no leaked rows |
| Stored XSS payload in a user name | absent from rendered HTML; email payload remains data |
| Shared CSRF token | present on the login Inertia response |
| Pharmacist password change | session version increments; password verifies |
| Privilege fields on medicine create | `is_active` / `archived_at` mass-assignment ignored |

Existing suites already cover brute-force throttling, notification ownership, session revocation,
append-only models and role abuse from API clients.

## 3. Responsive matrix

Every portal is exercised in Dusk across **360 / 640 / 768 / 992 / 1440**, and Part 9 adds **1024**:

| Role | Routes |
|---|---|
| Guest | landing, directory, doctor profile, emergency, auth |
| Admin | 15 governance screens incl. as-patient preview |
| Patient | 11 portal screens incl. symptom checker |
| Doctor | 11 clinical screens incl. builder and AI panel |
| Pharmacist | 10 pharmacy screens incl. gate and receipt |

Each route asserts `scrollWidth <= clientWidth + 1`; Part 9 also asserts the fixed sidebar remains
visible at 1024 for all four portals.

## 4. WCAG 2.1 AA

Automated (Lighthouse/axe, both themes): **Accessibility 100, Best Practices 100, SEO 100** across the
guest, admin, patient, doctor and pharmacy screens; the final re-audit of admin/doctor/pharmacist/
patient dashboards, directory and key forms passed at 100 in both themes.

Manual / interaction checks:

* Skip link is the first tab stop.
* Modal focus enters the dialog, and **Escape** closes it.
* Theme switch is keyboard reachable; focus rings use the AA focus token.
* Tables convert to cards below 640; steppers go vertical below their breakpoint.
* Reduced-motion preference disables animation/transition.

Two real contrast defects found and fixed in this phase:

1. `★★★★★` empty stars used the border colour (≈1.5:1 in dark). Now `--mp-text-muted` (AA in both themes).
2. Theme changes animated nav/button text and background colours, briefly rendering dark text on dark
   backgrounds (axe captured ≈1.7:1). Nav colour transitions were removed; final guest pages are 100
   in both themes.

## 5. Performance

Added `2026_11_23_120000_add_part9_performance_indexes.php`:

* `prescriptions (status, created_at)` and `dispensed_at`
* `invoices (payment_status, created_at)`
* `appointments (doctor_id, status, date)`
* `medicines (is_active, archived_at, expiry_date)`

`tests/Feature/PerformanceTest.php` asserts the indexes exist and that the pharmacy queue and invoice
list query count stays flat while rows grow from 1 to 10 (eager loading + bounded counts).

The Vite production build remains split by page; the shared app chunk is ≈386 kB (≈129 kB gzip).

## 6. Bug fixes in this phase

* Removed the transient low-contrast theme transition described above.
* Empty star colour moved to the AA muted token.
* End-to-end checks confirmed no negative waiting-hour output after the Part 8 fix.
* Validation and ownership paths re-verified through the journey and security suites.

## 7. Final verification

```bash
php artisan test --compact      # 185 passed, 2,247 assertions
php artisan dusk                # 38 passed, 482 assertions
npm run typecheck && npm run build
```

## 8. Known limitations / future work

* Email/SMS transport remains out of scope; in-app notifications and Reverb are live.
* AI is intentionally optional and advisory; the demo runs the deterministic fallback without a key.
* Partial dispensing is rejected by design; a future schema could add a partial-dispense state.
* Production deployment would add queue workers, a scheduler for nightly stock sweeps and external
  backup storage; the application already exposes the admin data tools.

## 9. Demo script (single pass)

1. Patient registers → books a slot → sees the pending request.
2. Doctor accepts + completes → prescribes (safety panel, optional AI summary).
3. Pharmacist opens the gate → verifies → dispenses → invoice appears with stock ledger entries.
4. Pharmacist marks the invoice paid → workflow reaches **Discharged**.
5. Patient opens prescriptions/bills and downloads both PDFs.
6. Admin reviews the activity log and clinical/billing records.