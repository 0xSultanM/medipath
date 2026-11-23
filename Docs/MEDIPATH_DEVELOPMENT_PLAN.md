# MediPath: AI-Powered Doctor Appointment and Medicine Safety System
## Complete Development Plan — CSE-479 Lab Project
**Version:** 1.0 · **Date:** 23 Nov 2026 · **Source docs:** `project-details.txt` (full functional spec), Use Case Diagram (UML)

---

## 1. UML Use Case Diagram — Analysis

### 1.1 Actors identified in the diagram
| # | Actor | Type | Notes |
|---|-------|------|-------|
| 1 | **Admin** | Primary | Clinic administrator / front-desk superuser |
| 2 | **Guest** | Primary | Unauthenticated visitor |
| 3 | **Registered Patient** | Primary | Generalizes Guest capabilities (inherits Register/Login/Browse/Chat) |
| 4 | **Doctor** | Primary | Verified clinical provider |
| 5 | **Pharmacist** | ⚠️ *Missing from diagram* | Fully specified in the functional doc (19 functions) but absent from the use case diagram. **Recommendation: add Pharmacist actor + use cases (View Pending Prescriptions, Dispense & Deduct Stock, Manage Inventory, Create Bill, Track Workflow) to the UML before submission.** |

### 1.2 Use cases per subsystem (as drawn)
**Admin Functions:** Manage Users · Verify Doctors · View Reports · Manage Chatbot FAQs
**Guest & Patient Functions:** Register · Login · Chat with FAQ Chatbot · Browse Doctor List · Show Emergency Alert · Add Allergies & Current Medicines · Manage Profile · Book Appointment · Cancel Appointment · View Appointment History · Give Review & Rating · Check Symptoms (AI)
**Doctor Functions:** Manage Doctor Profile · Manage Schedule · Check Medicine Safety (AI) · Accept / Reject Appointment · View Appointment Requests · Add Prescription Notes · View Common Side Effects · Check Patient Allergy · Check Drug Interaction

### 1.3 Relationships (as drawn)
| Relationship | Type | Meaning |
|---|---|---|
| Browse Doctor List → Search & Filter Doctors | «include» | Filtering is a mandatory part of browsing |
| Check Symptoms (AI) → Get Specialist Recommendation | «include» | Every symptom check produces a specialty recommendation |
| Show Emergency Alert ⇢ Check Symptoms (AI) | «extend» | Emergency escalation triggers conditionally on red-flag detection |
| Check Medicine Safety (AI) → View Common Side Effects / Check Patient Allergy / Check Drug Interaction | «include» | Safety check always runs all three deterministic sub-checks |
| Check Medicine Safety (AI) ⇢ Add Prescription Notes | «extend» | Clinical notes are an optional extension of the safety/prescribing flow |
| Accept / Reject Appointment ⇢ View Appointment Requests | «extend» | Responding extends the request-queue use case |

### 1.4 Traceability: Use cases → functional spec IDs (101 specs total)
| Module | Spec count | Spec IDs |
|---|---|---|
| Admin | 26 | adminAuthAndLogout … adminManageProfileAndSecurity (a–z) |
| Guest | 10 | guestPublicAccessAndSessionGuard … guestViewPendingApprovalScreen (a–j) |
| Patient | 23 | patientSessionLoginAndLogout … patientManageSettings (a–w) |
| Doctor | 23 | doctorVerifiedLoginAndLogout … doctorManageSettings (a–w, incl. both allergy & interaction checkers) |
| Pharmacist | 19 | pharmacistApprovedLoginAndLogout … pharmacistManageSettings (a–s) |

Key cross-cutting invariants repeated across the spec (must be enforced globally):
1. **Five roles only** — Guest, Patient, Doctor, Pharmacist, Admin; role always read from DB, never hardcoded.
2. **Server-side everything** — pagination, filtering, KPI aggregation, currency formatting (global currency setting, zero hardcoded `$`), ownership checks.
3. **Atomic transactions** — registration, booking, prescribing, dispensing+billing+discharge, role changes, archives.
4. **Immutable patient ID** — all clinical/financial records keyed by patient ID, never by name.
5. **Immutable activity log** — append-only audit trail for every critical action.
6. **Deterministic safety first, AI second** — red-flag/allergy/interaction checks are rule-based DB lookups; AI is decision-support with graceful fallback + Retry.
7. **Responsive + accessible** — mobile-first, WCAG 2.1 AA, card-stacking <640px, drawers <768px, tested to 360px, 44×44px touch targets, skeleton/empty/error+Retry states on every data view.

---

## 2. Technology Stack — Evaluation & Recommendation

### 2.1 Candidate architectures
| Option | Stack | Responsive | Dev speed (student team) | Server-side control (spec demands) | Deployment simplicity | Verdict |
|---|---|---|---|---|---|---|
| **A (Recommended)** | **Laravel 12 monolith + Blade + Bootstrap 5.3 + jQuery 3.7 + Vite** | ✅ Bootstrap grid mobile-first | ✅ Fastest; matches course skills (PHP/HTML/CSS/JS) | ✅ Full SSR control: pagination, KPIs, currency, RBAC | ✅ Runs on cheap shared host/VPS | **✔ BUILD WITH THIS** |
| B | Laravel + Inertia.js + React + Bootstrap | ✅ | ⚠️ Medium; needs React build pipeline | ✅ | ⚠️ Needs Node build on server | Good upgrade path if team knows React well |
| C | Laravel pure API + React SPA | ✅ | ❌ Slow; double codebase (API + SPA), auth/CORS complexity | ⚠️ Must re-implement guards in SPA | ❌ Two deployments | Overkill |
| D | Laravel pure API + Angular SPA | ✅ | ❌ Slowest; steep learning curve, heavy boilerplate | ⚠️ | ❌ | Not recommended |
| E | Blade + jQuery only (no Bootstrap) | ❌ Hand-rolled CSS risks inconsistent responsiveness | — | ✅ | ✅ | Rejected — spec demands rigorous responsive behavior |

### 2.2 Recommended stack (Option A) — details
| Layer | Technology | Purpose |
|---|---|---|
| Language/Runtime | **PHP 8.3+** | Server runtime |
| Backend framework | **Laravel 12** | MVC, Eloquent ORM, migrations, middleware, validation, queues, scheduler, event broadcasting |
| Database | **MySQL 8.0 / MariaDB 10.11** | Relational store with row-locking (`SELECT … FOR UPDATE`), check constraints, transactions |
| Templating | **Blade + Blade components** | SSR pages; reusable components = DoctorCard, DataTable, SkeletonBlock, ErrorRetry, StatusBadge, KpiCard, ChatbotWidget, EmergencyBanner |
| CSS framework | **Bootstrap 5.3** | Responsive grid/utilities/components; breakpoints mapped to spec (360 / 640 / 768 / 992 / 1200) |
| JS | **jQuery 3.7** (AJAX, debounced search, dynamic slot loading, filter drawer) + **vanilla JS modules** via Vite | Progressive enhancement; no SPA complexity |
| Charts | **Chart.js 4** | Admin analytics, doctor leaderboard, revenue breakdowns |
| Asset pipeline | **Vite** (ships with Laravel) | Compile SCSS/JS, cache busting |
| PDF generation | **barryvdh/laravel-dompdf** | Shared Prescription PDF + Invoice PDF builders (one template, all roles) |
| CSV | Laravel streamed responses + custom RFC-4180 writer w/ formula-injection neutralization | Exports/imports |
| Full-text search | **MySQL FULLTEXT index** (or Laravel Scout database driver) | FAQ chatbot retrieval, doctor/patient/medicine search |
| AI integration | **Laravel HTTP client → OpenAI-compatible API** (structured JSON output) behind `AiService` with deterministic fallback | Symptom triage, medicine safety evaluation |
| Auth | Laravel session auth (database sessions) + HTTP-only cookies + custom middleware | RBAC gates, pending-token flow, session revocation |
| Queues/Scheduler | Database queue + `schedule:run` cron | Emails, notification fan-out, daily expiry/stock alert sweep |
| Testing | **Pest (PHPUnit)** feature + unit tests | Concurrency, RBAC, atomicity proofs required by spec |
| Repo/CI | Git + GitHub (branch per module, PR reviews) | Team workflow |

### 2.3 Responsive strategy (applies to every screen)
- **Mobile-first** Bootstrap grid; container breakpoints: `≤360px` stacked chips/cards & one-column doctor grid; `<640px` all tables convert to stacked cards; `<768px` navbar → keyboard-accessible slide-over drawer, secondary filters → drawer, sidebars → offcanvas; `768–991px` two-column doctor grid; `≥992px` three-column grid + fixed sidebar.
- Touch targets ≥ 44×44px; skip-to-content link; visible focus rings; AA contrast; `aria-live` for chatbot/notifications; skeleton loaders sized to final layout (no CLS).
- Every data view ships 4 states: **loading skeleton · empty state · error + Retry · success**.

---

## 3. System Architecture

### 3.1 Application structure (Laravel monolith, module-per-role)
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Guest/        (PublicController, AuthController, DoctorDirectoryController, EmergencyController, ChatbotController)
│   │   ├── Patient/      (Dashboard, Profile, Allergy, Booking, Prescription, Billing, Review, SymptomChecker, Settings, Notification)
│   │   ├── Doctor/       (Dashboard, Schedule, Appointments, Patients, Prescribing, MedicineRef, Reviews, Settings, Notification)
│   │   ├── Pharmacist/   (Dashboard, Dispensing, Inventory, StockAlerts, Billing, Workflow, Settings, Notification)
│   │   └── Admin/        (Dashboard, Users, Approvals, Verification, Doctors, Patients, Appointments, Records, Revenue, Reports, ActivityLog, Faq, Reviews, SafetyRules, Settings, DataManagement, Security)
│   ├── Middleware/       (EnsureRoleIs, EnsureDoctorVerified, EnsurePharmacistApproved, EnsurePendingToken, EnsureNotPending, ThrottleLogins, LogActivity)
│   └── Requests/         (FormRequest per workflow — server-side validation mirrors client)
├── Services/
│   ├── SettingsService          (global currency/locale/tz/date-format/revenue% — cached, broadcast on change)
│   ├── CurrencyFormatter        (single source of truth; NO "$" literals anywhere)
│   ├── ActivityLogService       (append-only)
│   ├── NotificationService      (in-app fan-out per event)
│   ├── SlotService              (schedule+leave-aware availability, slot generation, conflict checks)
│   ├── Safety/
│   │   ├── RedFlagEngine        (deterministic keyword match vs emergency_alert_rules)
│   │   ├── AllergyCrossCheck    (ingredient/drug-class/alias vs patient_allergies)
│   │   ├── InteractionChecker   (pairwise vs drug_interactions matrix)
│   │   └── SideEffectRepository
│   ├── Ai/
│   │   ├── AiClient             (provider-agnostic, timeout, JSON-schema)
│   │   ├── SymptomTriage        (Stage-2 only after RedFlagEngine clears)
│   │   └── MedicineSafetyEval
│   ├── Pdf/  (PrescriptionPdfBuilder, InvoicePdfBuilder — shared by all roles)
│   ├── Csv/  (SafeCsvWriter w/ formula neutralization, CsvImportDryRunner)
│   └── Inventory/ (StockLedger, DispenseTransaction — row locks + CHECK(stock>=0))
├── Models/  (User, Patient, Doctor, PharmacistProfile, Specialty, DoctorSchedule, DoctorLeave,
│             Appointment, Prescription, PrescriptionItem, Medicine, StockMovement, DrugInteraction,
│             PatientAllergy, PatientCurrentMedication, SymptomCheck, EmergencyContact, EmergencyAlertRule,
│             EmergencyAlertBanner, SymptomSpecialtyMap, Faq, Review, Notification, ActivityLog,
│             ApprovalQueueItem, CredentialDocument, Invoice, InvoiceItem, DispenseVerification,
│             Setting, UserPreference, PasswordResetToken)
└── Providers/
resources/views/
├── layouts/  (public.blade, patient.blade, doctor.blade, pharmacy.blade, admin.blade — each w/ responsive sidebar/drawer)
├── components/ (doctor-card, data-table, skeleton, error-retry, empty-state, status-badge, kpi-card,
│                chatbot-widget, emergency-banner, star-rating, slot-picker, stepper-6stage, confirm-modal, filter-drawer)
└── {role}/…  screens per module
routes/  web.php (page routes per role prefix) · api.php (internal JSON endpoints: slots, searches, chatbot, ai-checks, notification poll)
database/ migrations · seeders (Specialties, Medicines+interactions+side-effects, FAQs, EmergencyRules, SymptomSpecialtyMap, demo users per role)
```

### 3.2 Route & middleware map
| Prefix | Middleware | Contents |
|---|---|---|
| `/` (public) | guest-safe, throttle(chatbot) | Home, doctor directory/profile, emergency info, login, register, forgot/reset, pending-approval, chatbot API |
| `/patient/*` | `auth` + `role:patient` + `not-pending` | Patient workspace |
| `/doctor/*` | `auth` + `role:doctor` + `doctor.verified` | Doctor workspace |
| `/pharmacy/*` | `auth` + `role:pharmacist` + `pharmacist.approved` | Pharmacy workspace |
| `/admin/*` | `auth` + `role:admin` + admin inactivity timeout | Admin workspace |
| `/pending` | `pending.token` | Account Pending Approval holding screen |

### 3.3 Database schema (34 tables — core columns)
| Table | Key columns & constraints |
|---|---|
| users | id, name, email **uniq**, phone, password, role enum(patient,doctor,pharmacist,admin), status enum(pending,approved,rejected,suspended,archived), last_login_at, timestamps, softDeletes |
| patients | id **immutable**, user_id **uniq nullable** FK, walk_in_code **uniq nullable**, full_name, phone, age, gender, primary_condition, workflow_status enum(registered,active_visit,prescribed,dispensed,discharged,archived), archived_at |
| doctor_profiles | user_id **uniq** FK, specialty_id FK, years_exp 0–70, consultation_fee decimal>0, license_number, license_authority, verification_status enum(unverified,under_review,verified,revoked), bio, photo_path, archived_at |
| pharmacist_profiles | user_id **uniq** FK, license_number, license_authority, approval_status, archived_at |
| specialties | id, name **uniq**, active |
| doctor_schedules | doctor_id FK, weekday 0–6, start_time, end_time, slot_minutes (15/20/30), break_start, break_end; **no overlapping blocks per weekday (validated)** |
| doctor_leaves | doctor_id FK, start_date, end_date (end≥start), reason |
| appointments | id, patient_id FK, doctor_id FK, date, start_time, end_time, status enum(pending,accepted,rejected,cancelled,completed), visit_reason, rejection_note, cancel_reason/actor, reschedule_count, completed_at; **unique(doctor_id,date,start_time) enforced for active statuses via locking + filtered unique index (generated column `active_slot_key`)** |
| prescriptions | id, patient_id FK, doctor_id FK (**server-derived, never client-supplied**), pharmacist_id nullable FK, status enum(pending_dispense,dispensed,completed), license_snapshot, patient_notes, pharmacy_notes, override_justification, dispensed_at |
| prescription_items | prescription_id FK, medicine_id FK, dosage, schedule, duration_days, quantity |
| medicines | id, name, generic_name, active_ingredient, drug_class, brand_aliases json, category, manufacturer, batch_number, unit_price decimal>0, stock_qty int **CHECK(stock_qty>=0)**, min_stock_threshold, expiry_date, side_effects_common, side_effects_serious, administration_guidance, status active/archived |
| stock_movements | id, medicine_id FK, type enum(stock_in,stock_out,adjustment), prev_qty, change_qty, new_qty, actor_role, actor_id, reference morph, created_at (immutable ledger) |
| drug_interactions | medicine_a_id FK, medicine_b_id FK, severity enum(minor,moderate,major,contraindicated), explanation |
| patient_allergies | patient_id FK, substance, category enum(medicine,food,environmental), severity enum(mild,moderate,severe), reaction_notes, added_by_role, active; **uniq(patient_id,substance) active** |
| patient_current_medications | patient_id FK, medicine_name, dosage, frequency, start_date, active |
| symptom_checks | id, patient_id FK, inputs json, red_flag_triggered bool, ai_result json, urgency enum, mapped_specialty_id FK, created_at (owner-only reads) |
| emergency_contacts | id, label, phone, category, sort |
| emergency_alert_rules | id, phrase, category (cardiac/respiratory/stroke/bleeding/anaphylaxis/pediatric/crisis), severity, action_directive, active |
| emergency_alert_banners | id, title, message, starts_at, ends_at, active |
| symptom_specialty_maps | id, symptom_keyword/category, specialty_id FK, priority; **uniq mapping** |
| faqs | id, question, answer, category, keywords, published bool, sort_order; FULLTEXT(question,answer,keywords) |
| reviews | id, appointment_id **uniq** FK, patient_id FK, doctor_id FK, rating 1–5, comment, moderation_status enum(pending,approved,hidden_rejected), moderation_note |
| notifications | id, user_id FK, category, message, link, read_at, created_at |
| activity_logs | id, actor_user_id nullable, actor_role, action, target_type, target_id, detail json, severity, created_at — **append-only (no update/delete code paths)** |
| approval_queue_items | id, user_id FK, requested_role, license_details json, credential_doc_id, status enum(pending,approved,rejected), reviewer_id, review_reason, decided_at |
| credential_documents | id, user_id FK, path (private disk), mime, size, created_at |
| invoices | id, patient_id FK (**never name**), pharmacist_id FK, prescription_id nullable FK, currency_code snapshot, gross_subtotal, discount_kind, discount_value, net_total, payment_method enum(cash,card,mobile_banking,insurance,bank_transfer), payment_status enum(pending,paid,refunded), paid_at, refund_reason |
| invoice_items | invoice_id FK, medicine_id FK, quantity, unit_price, line_total |
| dispense_verifications | id, prescription_id FK, pharmacist_id FK, confirmed bool, note, created_at |
| settings | key **uniq**, value json, effective_from, updated_by — revenue_percentage **CHECK(0<=v<=100)**, currency_code, locale, timezone, date_format, clinic_name, pdf_header, patient_auto_approve, cancellation_cutoff_hours |
| user_preferences | user_id **uniq** FK, theme, language, timezone, date_format, notif_prefs json |
| password_reset_tokens | email, token_hash, expires_at (30 min), consumed_at |
| sessions | Laravel database sessions (enables instant revocation) |

### 3.4 AI & safety pipeline (spec-faithful)
```
Patient message / symptom form
   └─> Stage 1: RedFlagEngine (DB rules, deterministic) ──match──> Emergency Alert UI (halt booking, hotlines, ER directions)
   └─> Stage 2 (only if clear): AiClient → SymptomTriage (strict system prompt: no diagnosis, no dosing;
        JSON schema: explanations[], urgency, questions[], specialty_category)
   └─> Specialty cross-validation vs symptom_specialty_maps → real specialty → top-3 verified doctors preview
AI failure/timeout → deterministic fallback (top FAQ / rule-based urgency) + Retry button. Same pattern for Doctor Medicine Safety Eval.
```

---

## 4. Development Plan (14 weeks · 5 role work-packages + shared core)

### 4.0 Team assignment suggestion (4–6 members)
| Member | Work package |
|---|---|
| M1 (Lead) | Phase 0–1 core platform, Admin module, deployment, CI |
| M2 | Guest module + Patient module (booking/history/bills) + shared DoctorCard/filter components |
| M3 | Doctor module (schedule, prescribing, safety checks) |
| M4 | Pharmacist module (inventory, dispensing, billing, workflow tracker) |
| M5 | Patient AI features + chatbot + notifications + QA/accessibility/responsive matrix + report writing |
*(If 4 members: merge M5 into M2/M3.)*

### 4.1 Phase schedule
| Phase | Weeks | Scope (spec IDs) | Deliverables / Exit criteria |
|---|---|---|---|
| **0 — Foundation** | W1 | Repo, Laravel 12 + Bootstrap 5 + jQuery + Vite setup, CI, coding standards, design tokens, all 34 migrations, seeders, 5 responsive layout shells (public/patient/doctor/pharmacy/admin), global JS kit (skeleton/empty/error+Retry, debounce, currency formatter, confirm-modal) | App boots; layouts render at 360/768/1440; seed data loads |
| **1 — Core Auth & Platform Services** | W2–W3 | guest a,c,d · admin a,x,z · patient a,e,w · doctor a · pharmacist a (login/logout gates) + SettingsService, CurrencyFormatter, ActivityLogService, NotificationService, sessions/revocation, pending-token flow, rate limiting | Automated tests: role gates, pending interception, suspended/archived blocks, session revocation |
| **2 — Guest Module** | W3–W4 | guest b,f,g,h,i,j + chatbot e | Public directory w/ URL-synced filters, public profile w/ reviews+7-day availability, emergency page w/ offline fallback, chatbot widget w/ red-flag interceptor, registration atomic txn, pending-approval screen |
| **3 — Patient Module** | W5–W6 | patient b,c,d,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w | Booking w/ concurrency proof tests, cancel/reschedule, history, notifications, prescriptions+PDF, bills+PDF+search, reviews, allergies/meds, AI symptom checker + specialist rec + emergency escalation, settings |
| **4 — Doctor Module** | W7–W8 | doctor b–w | Schedule/leave builder w/ conflict warnings, request accept/reject/complete, dashboard KPIs, walk-in registration + claim codes, patient chart + 3-tab history, medicine live-stock search, allergy/interaction/side-effect/AI safety checks, prescription builder + notes + PDF, reviews, notifications, settings |
| **5 — Pharmacist Module** | W9–W10 | pharmacist b–s | Dashboard KPIs, pending queue, pre-dispense safety gate, **atomic Dispense+Bill+Discharge 7-step transaction** w/ rollback tests, inventory CRUD + restock ledger, stock & expiry alert consoles, invoice builder + PDF + payment status, 6-stage workflow tracker, notifications, settings |
| **6 — Admin Module** | W11–W12 | admin b–y (a,x,z done in P1) | Master dashboard + system overview, user mgmt + role change, approval queue, doctor verification + doctor CRUD, patient CRUD + portal, appointment oversight, clinical/billing oversight tabs, revenue % config, 7-section analytics + CSV/PDF export, activity log viewer, FAQ manager, review moderation, emergency & specialty mapping console, data export/import/backup + danger zone |
| **7 — Hardening & Release** | W13 | Full-suite Pest tests (RBAC matrix, double-booking race, negative-stock impossibility, double-dispense, invoice ownership isolation, CSV injection, currency formatting), WCAG 2.1 AA audit, responsive QA matrix (360/390/640/768/1024/1366/1920), perf pass (indexes, bounded queries), security pass (upload validation, private disks, CSRF, rate limits), UAT with demo data, user manual + project report | All P0 bugs closed; demo script rehearsed |
| **8 — Buffer / Demo** | W14 | Fixes, deployment to host, final presentation prep | Live URL + credentials sheet + report submitted |

### 4.2 Definition of Done (every function)
1. Server-side validation (FormRequest) mirrors client validation; DB constraints back both.
2. Ownership/role verified server-side; no client-supplied IDs trusted for patient/doctor/invoice/prescription ownership.
3. Bounded, paginated, indexed queries only; explicit column selects.
4. All four UI states (skeleton/empty/error+Retry/success) + responsive behavior at 360/640/768 verified.
5. Currency via `CurrencyFormatter`; dates via user/system timezone+format; role labels from DB.
6. Activity log entry written; notifications fired where specified.
7. Feature test covering happy path + at least one abuse/race path.

### 4.3 Risk register
| Risk | Mitigation |
|---|---|
| Scope (101 specs) exceeds semester time | Phase gates are demo-able vertical slices; MoSCoW: P6 analytics export & data-import are first candidates to simplify (keep UI + core txn) |
| Concurrency bugs (double-booking, negative stock) | DB unique keys + `CHECK(stock>=0)` + row locks from day one; dedicated race tests in W3 |
| AI API cost/availability | Deterministic Stage-1 rules always run; AI optional Stage-2 with fallback; cache responses; cap tokens |
| Team merge conflicts | Branch per module (`feat/patient-booking`), shared components owned by M2, weekly integration day |
| Hosting limits | Pagination everywhere, queued emails, scheduled alert sweep instead of cron-heavy jobs |

---

## 5. SECTION 04 — FIGMA MAKE PROMPT (copy–paste ready)

> **How to use:** Paste the **Master Prompt** into Figma Make first to generate the design system + core screens. Then run the **Follow-up Prompts** one at a time to generate the remaining role screens. Replace `{{CURRENCY}}` mentally with your clinic currency — the design must never show a literal `$`.

### 5.1 MASTER PROMPT (paste first)

```
Design a complete, production-ready, mobile-first responsive web application UI for
"MediPath: AI-Powered Doctor Appointment and Medicine Safety System" — a clinic
platform with five roles: Guest (public), Patient, Doctor, Pharmacist, and Admin.

DESIGN SYSTEM
- Style: clean clinical healthcare SaaS; calm, trustworthy, high-legibility.
- Colors: primary teal #0E7C86; deep navy ink #0B2545 for headings/sidebar;
  background #F6F9FB; surface #FFFFFF; success #16A34A; warning #D97706;
  danger #DC2626; emergency red #B91C1C (reserved ONLY for emergency banners/CTAs);
  info blue #2563EB. Provide light theme AND dark theme variants.
- Typography: "Inter" (or Manrope) — H1 32/40 bold, H2 24/32 semibold, H3 20/28 semibold,
  body 16/24 regular, caption 13/18; tabular numerals for all money/IDs.
- Spacing: 8pt grid; card radius 12px; input radius 8px; chips/pills fully rounded;
  soft elevation shadows; 1px borders #E2E8F0.
- Money is ALWAYS shown as a token like {{CURRENCY}} 1,500.00 — never a hardcoded $ sign.
- Breakpoints: generate every screen at Mobile 360×780, Tablet 768×1024, Desktop 1440×900.
  Below 640px all tables become stacked cards; below 768px navbars/sidebars become
  slide-over drawers; doctor grids go 1-col mobile / 2-col tablet / 3-col desktop.
- Accessibility: WCAG 2.1 AA contrast, 44×44px touch targets, visible focus rings,
  skip-to-content link, aria-live regions for chat and notifications.

GLOBAL COMPONENT LIBRARY (design each with all 4 states: skeleton, empty, error+Retry, success)
1. Public top navbar: logo "MediPath", links Find Doctors / Emergency Info / Login / Register,
   permanent high-contrast Emergency link; collapses to keyboard-accessible slide-over <768px.
2. Authenticated role sidebar (Patient/Doctor/Pharmacy/Admin variants) with icon+label items,
   active state, unread notification bell badge; offcanvas drawer on mobile.
3. KPI card (value, label, delta, sparkline slot) + skeleton variant.
4. Data table → responsive card-stack conversion; column headers, sortable icons, pagination bar.
5. Filter bar + mobile filter drawer: debounced search input, specialty select, availability
   weekday picker, "Available Today" toggle, min-rating slider, sort select, removable filter
   tags, "Clear All Filters".
6. Status badges: appointment (pending/accepted/rejected/cancelled/completed); stock
   (healthy/low/critical/out-of-stock); expiry (ok/upcoming/critical/expired); payment
   (paid/pending/refunded); account (pending/approved/rejected/suspended/archived).
7. Doctor card: photo/initials fallback, name, real specialty chip, years experience,
   star rating + review count, {{CURRENCY}} fee, next-available line, action slot
   (View Profile / Book Appointment).
8. Date picker + time-slot chip grid (disabled states for past/leave/booked slots).
9. Star rating input + 5→1 distribution bars.
10. Notification bell + dropdown panel + full paginated notifications page.
11. Floating FAQ chatbot widget: header with PINNED non-dismissible medical disclaimer,
    message stream, suggested-question chips, full-height mobile drawer, and an in-stream
    high-contrast EMERGENCY ALERT banner variant with one-tap call buttons.
12. Emergency page blocks: big one-tap callable phone cards, categorized red-flag symptom
    accordion (cardiac/respiratory/stroke/bleeding/anaphylaxis/pediatric), time-bounded
    clinic alert banner, offline-fallback notice.
13. Confirmation modals incl. "type the name to confirm" danger variant with dependency
    counts (e.g., 12 appointments · 34 prescriptions linked).
14. Six-stage clinical workflow stepper (Registration → Prescription → Stock Check →
    Dispense → Billing → Discharged), horizontal on desktop / vertical on mobile.
15. Toasts, inline field errors, saving spinners, unsaved-changes warning, Retry banners.
16. PDF download buttons with loading state (Prescription PDF / Invoice PDF).

SCREENS — GUEST/PUBLIC
Landing hero with search + emergency strip; Doctor Directory (grid + filters + pagination);
Doctor Public Profile (header, verified badge, 7-day availability table, paginated approved
reviews, "Sign In to Book"); Emergency Information page; Login; Register (multi-role with
conditional Doctor/Pharmacist license+upload fields and Patient walk-in claim code);
Forgot/Reset Password; Account Pending Approval (3-stage progress: Submitted → Admin Review →
Activation, Check Status button, rejection reason panel).

SCREENS — PATIENT PORTAL (sidebar: Dashboard, Find Doctors, My Appointments, AI Symptom
Checker, My Prescriptions, My Bills, Allergies & Medicines, Reviews, Notifications, Settings)
Dashboard KPI row + next appointment; Booking flow (doctor select → date → slot chips →
reason → confirm); Appointment history with status tabs and contextual actions
(Cancel/Reschedule/Leave Review/View Prescription); AI Symptom Checker wizard (disclaimer
first, structured inputs: symptoms multi-select, body region, duration, severity, onset,
consent toggle) → results (urgency guidance, questions to ask, recommended specialty,
top-3 doctor preview, Browse Specialists / Book buttons) → Emergency escalation modal
variant; My Prescriptions (expandable medicine rows + Download PDF); My Bills (totals card,
invoice search by ID, Download PDF); Allergies & Medications manager (severity chips);
Profile; Change Password; Settings (notifications/theme/language/timezone/date format).

SCREENS — DOCTOR PORTAL (sidebar: Dashboard, Schedule, Appointment Requests, Patients,
Medicines, Prescriptions, Reviews, Notifications, Settings)
Dashboard (4 KPIs + today's timeline + pending requests panel); Weekly schedule builder
(weekday cards, time blocks, slot duration, breaks) + leave manager with conflict warning
modal; Appointment Requests queue (tabs Pending/Today/Upcoming/Completed/Cancelled, inline
Accept/Reject-with-reason/Mark Completed); Patient list + Clinical Detail (high-visibility
Allergy Alert strip, current meds, 3-tab history: Prescriptions/Appointments/Billing);
Walk-In Registration form with generated claim code receipt; Prescription Builder
(patient header, live-stock medicine search with side-effect drawer, dosage/schedule/
duration/quantity rows, live Allergy Conflict + Drug Interaction warning panels with
override justification field, patient-facing vs pharmacy-facing notes, Save + Download PDF);
My Reviews; Settings.

SCREENS — PHARMACY PORTAL (sidebar: Dashboard, Pending Prescriptions, Medicine Inventory,
Stock & Expiry Alerts, Billing & Invoices, Workflow Tracker, Notifications, Settings)
Dashboard (3 KPIs + oldest-5 pending queue + urgent stock queue); Pending Prescriptions
queue with stock-availability flags and Review & Dispense; Pre-Dispense Safety Gate panel
(allergy conflict / interaction / override note / expired batch / insufficient stock blocks +
pharmacist verification checkbox); Dispense & Bill builder (itemized rows, live stock,
discount, payment method, live totals in {{CURRENCY}}) → success receipt with Invoice PDF;
Inventory table + Add/Edit Medicine forms + Restock modal; Stock Alerts console;
Expiry Alerts tabs (Expired/Critical ≤30d/Upcoming 31–90d); Invoices directory with payment
status update modal; six-stage Workflow Tracker; Settings.

SCREENS — ADMIN PORTAL (sidebar: Dashboard & Overview, User Approvals & Doctor Verification,
Doctors, Patients & Patient Portal, Appointments & Clinical Records, Revenue & Analytics,
Chatbot FAQs & Reviews, Emergency & AI Rules, System Settings, Data Management, Admin Security)
Master Dashboard (5 KPIs + commission revenue + date-range toggles); System Overview health
panel with drill-down links; User Management directory + role change modal; Pending Sign-Up
Approval queue with credential document preview + approve/reject-with-reason; Doctor
Verification workspace (3 decisions: Approve & Verify / Request Resubmission / Revoke);
Doctor & Patient CRUD + soft-archive confirmation modals with dependency counts; Patient
Portal (search + prescription/billing history tabs); Global Appointments oversight;
Clinical & Billing records tabs with PDF buttons; Revenue % configuration; Reports &
Analytics (7 sections: monthly revenue, patient growth, prescription completion, invoice
collection, stock health & expiry risk, appointment conversion/cancellation, top-doctor
leaderboard) with CSV/PDF export buttons; immutable Activity Log viewer with facet filters;
FAQ Knowledge Base manager; Review moderation queue; Clinical Safety console (Emergency
contacts & red-flag rules tab + Symptom-to-Specialty mapping tab); Global System Settings
(currency/locale/timezone/date format/auto-approve toggle/cancellation cutoff);
Data Management (CSV export, two-step dry-run import, backup snapshot, danger zone with
password re-entry); Admin Profile & Security (active sessions + revoke others).

OUTPUT: organized Figma pages per role, named components in a library, auto-layout
everywhere, variables for colors/spacing/typography (light+dark modes), and realistic
sample medical data (no lorem ipsum).
```

### 5.2 FOLLOW-UP PROMPTS (run one at a time in Figma Make)
1. "Generate the Guest flow: Landing, Doctor Directory with filters at 360/768/1440, Doctor Public Profile with 7-day availability and approved reviews, Emergency Info page with one-tap call cards."
2. "Generate Register + Login + Forgot/Reset + Account Pending Approval screens, including the conditional Doctor/Pharmacist license upload fields and the 3-stage pending progress indicator."
3. "Generate the floating FAQ chatbot widget: closed FAB, open drawer with pinned disclaimer, normal answer state, and the in-stream emergency red-flag alert state with call buttons."
4. "Generate the Patient booking journey: doctor select, leave-aware date + slot chip picker with disabled states, reason step, confirmation summary, slot-taken error state."
5. "Generate the Patient AI Symptom Checker: disclaimer gate, structured intake form, results screen with urgency + recommended specialty + top-3 doctors, and the full-screen emergency escalation modal."
6. "Generate Patient My Appointments (status tabs + contextual actions), My Prescriptions (expandable rows + PDF button), My Bills (totals + ID search + PDF)."
7. "Generate Doctor weekly schedule builder + leave manager, including the affected-appointments conflict warning modal."
8. "Generate Doctor Appointment Requests queue with inline Accept / Reject-with-reason / Mark Completed and mobile card variant."
9. "Generate the Doctor Prescription Builder with live-stock medicine search, side-effect drawer, allergy conflict + drug interaction warning panels, override justification, and dual notes fields."
10. "Generate Pharmacy Pending Prescriptions queue + Pre-Dispense Safety Gate + Dispense & Bill builder + success receipt with Invoice PDF button."
11. "Generate Pharmacy Inventory table, Add Medicine form, Restock modal, Stock Alerts console, and Expiry Alerts tiered tabs."
12. "Generate the six-stage Clinical Workflow Tracker (horizontal desktop / vertical mobile) with click-through jump actions."
13. "Generate Admin Master Dashboard + System Overview with KPI cards, date-range toggles, skeleton and error+Retry states."
14. "Generate Admin approval & verification queues: Pending Sign-Ups with credential preview, Doctor Verification with 3 decision actions, review moderation queue."
15. "Generate Admin Reports & Analytics: 7 report sections with sortable print-ready tables, charts, and CSV/PDF export controls; plus the immutable Activity Log viewer."
16. "Generate Admin Clinical Safety console (emergency rules + symptom-to-specialty mapping tabs) and Global System Settings with currency/locale controls."
17. "Apply dark mode variables to all generated screens and verify AA contrast on badges, banners, and emergency blocks."

---

## 6. Immediate Next Steps (start building)
1. ✅ Approve stack (Option A) and team work-packages (§4.0).
2. Run Figma Make prompts (§5) → export design tokens → implement as SCSS variables.
3. Scaffold Phase 0: `composer create-project laravel/laravel medipath`, add Bootstrap/jQuery via Vite, create all 34 migrations + seeders, build the 5 layout shells and the global JS state-kit.
4. Stand up GitHub repo with branch protection + PR template referencing spec IDs (e.g., `patient-l`).
5. Begin Phase 1 with the RBAC test matrix written **first** (tests define the gates).
