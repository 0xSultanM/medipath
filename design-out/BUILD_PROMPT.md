# MediPath — Master Build Prompt (single, chunked, continuation-gated)

> Paste everything inside the CODE BLOCK below into your coding agent as ONE prompt.
> It builds the website in 10 parts, mapping `design-out/` artifacts onto the phases in
> `DEVELOPMENT_PLAN.md`, and forces a stop-for-review after every part.

```
You are the senior full-stack engineer building "MediPath: AI-Powered Doctor Appointment
and Medicine Safety System". You will implement it in TEN sequential parts. Two documents
govern this work, with a strict precedence rule:

SOURCE OF TRUTH & PRECEDENCE
- Architecture, stack, database schema, phase/sprint plan, routes and services:
  DEVELOPMENT_PLAN.md  (the "PLAN")
- UI layer ONLY (colors, type, spacing, radii, shadows, components, states, responsive
  behavior, accessibility): design-out/
  (the "DESIGN"):
    • design-out/tokens.json + design-out/tokens.scss — design tokens (light+dark).
      Compile into resources/sass/_tokens.scss and import in every stylesheet; expose the
      same values as CSS custom properties exactly like design-out/prototypes/assets/tokens.css.
    • design-out/COMPONENT-SPEC.md — the 17-component global library: layout rules,
      variant properties (State/Tone/Size/Role/Responsive), the 4 data states
      (skeleton/empty/error+Retry/success), tokens, a11y notes. Every UI element you build
      MUST trace to its spec section (§n) — cite the section in the component file header.
    • design-out/prototypes/**/*.html — approved high-fidelity screens at 360/768/1440.
      They are your visual acceptance test: your rendered page must match the prototype's
      structure, states and responsive behavior (data views with state switches, drawers,
      modals, steppers, slot picker, chatbot, safety panels).
    • design-out/DESIGN-RATIONALE.md — decision mapping [P1–P10] + WCAG 2.1 AA table.
      Follow it for edge-case decisions and reuse its contrast pairs verbatim.
- PRECEDENCE RULES:
  1) If the PLAN's §7 (old Figma prompt) conflicts with DESIGN on any color/typography/
     spacing/theme value — DESIGN WINS. The approved palette is teal #0E7C86 / ink
     #0B2545 / bg #F6F9FB / border #E2E8F0 / success #16A34A / warning #D97706 /
     danger #DC2626 / info #2563EB / emergency #B91C1C, plus AA-derived text tones from
     tokens.json (e.g., success-text #166534, warning-text #975307, danger-text #C11E1E,
     dark-mode tints). Do NOT use the blue #2563EB-as-primary palette from PLAN §7.
  2) Architecture questions (stack, DB, middleware, services, phases) — PLAN wins.
  3) Never invent UI values: no literal hex/px outside tokens; query DESIGN first.

STACK (from PLAN §2, primary option)
Laravel 11 + React 18 + TypeScript + Bootstrap 5.3 + Vite, MySQL 8, Redis (sessions/
queues), Reverb/Echo notifications, Sanctum+session auth, DomPDF, Scout/MySQL FULLTEXT.
If the team opts for PLAN §2's "Alternative (Simpler)" Blade+Livewire path, keep ALL UI
contracts identical (same tokens, same component specs, same states).

GLOBAL UI CONTRACT — enforced in EVERY part (violations are build failures)
G1. Money renders {{CURRENCY}} + tabular numerals; the symbol comes from
    system_settings.currency_code via a single CurrencyFormatter service. ZERO literal
    "$" in UI, APIs, PDFs or CSVs. (DESIGN: tokens.json meta.currency, PLAN §5.)
G2. Emergency red #B91C1C is scoped to emergency UI only (red-flag escalation modal,
    in-stream chat alert, emergency page/strip, red-flag rule/event badges, hotline CTAs).
    Routine destructive/error UI uses danger #DC2626. Ledger: DESIGN-RATIONALE §3.
G3. Every data-bound view ships all 4 states — skeleton (final-layout sized, no CLS),
    empty (noun + why + CTA), error+Retry (cause + last-known time, keeps filters),
    success. Implement as component variant State=… per COMPONENT-SPEC §0/§17.
G4. Responsive thresholds: <640 tables→stacked cards (data-label pseudo-headers);
    <768 navbar→slide-over drawer; <992 role sidebar→drawer; doctor grid 1/2/3 cols;
    modals full-height bottom-sheet on mobile; touch targets ≥44×44.
G5. WCAG 2.1 AA: contrast pairs from DESIGN-RATIONALE §2 (both themes); visible focus
    rings (--mp-focus-ring); skip-to-content link per layout; aria-live=polite on chat
    stream + notification region; role="alert"/"status"/"alertdialog" per SPEC §15/§13;
    status conveyed by dot+text+icon, never color alone; prefers-reduced-motion disables
    shimmer/pulse.
G6. Role labels, specialty names, statuses render from DB enums/tables (data-src pattern
    in prototypes). No hardcoded role/specialty strings in components — they are props.
G7. Themes: full light + dark from tokens.scss mode maps; toggle via user_preferences
    (System→override chain); bootstrap 5.3 data-bs-theme wired to --mp-* variables.
G8. Server-side everything: pagination, filters (URL-synced), KPI aggregation, currency
    formatting, ownership checks. Client trust nothing (doctor_id/patient_id from session
    or signed params). Deterministic safety (red-flag/allergy/interaction) ALWAYS before
    AI; AI has fallback + Retry (PLAN services AiSymptomChecker/EmergencyRuleEngine).

WORK PROTOCOL — CONTINUATION GATE (critical)
- Implement exactly ONE part at a time, in order. Within a part, work sprint-by-sprint
  per the PLAN, but do not start the next part on your own.
- At the END of each part, STOP and output a "PART n COMPLETION REPORT":
    (a) files created/modified (tree view of relevant paths),
    (b) which COMPONENT-SPEC §s and prototype files were consumed by each screen,
    (c) how all 4 states are demonstrable on every data view (URL params, state switcher
        prop, or feature flag),
    (d) tests added + how to run them (Pest feature tests for role gates + one abuse/race
        path per part; Dusk/browser checks at 360/768/1440 for the part's screens),
    (e) the self-check line: [360/768/1440] [4 states] [AA] [{{CURRENCY}} only]
        [no hardcoded roles] [emergency red scoped] [PLAN phase coverage vs sprint list],
    (f) any deviation from PLAN or DESIGN with justification,
    (g) then ask: "Reply 'continue' (with any corrections) to proceed to PART n+1."
- Wait for the user's "continue". Incorporate any corrections first.

────────────────────────────────────────────────────────────────────────────────
PART 0 — Foundation (PLAN Phase 0, sprints 0.1–0.4)
- Scaffold Laravel 11 + React 18/TS + Bootstrap 5.3 + Vite + MySQL + Redis per PLAN §2/§6
  (structure exactly as PLAN §6 tree). Migrations for ALL tables in PLAN §4 with CHECK
  stock≥0, unique(doctor,date,active-slot-key), immutable patient IDs, softDeletes,
  append-only activity_log (no update/delete routes). Seeders: specialties, medicines +
  drug_interactions + side effects, emergency_rules, symptom_specialty_map, FAQs, demo
  users per role (realistic clinical data, no lorem).
- Design system install: port design-out/tokens.scss → resources/sass/_tokens.scss;
  emit CSS custom properties like design-out/prototypes/assets/tokens.css; map Bootstrap
  5.3 CSS variables (--bs-*) to --mp-* for components you keep from Bootstrap.
- Build the GLOBAL component library as React components (names per COMPONENT-SPEC §1–16):
  PublicNavbar, RoleSidebar(Role=), KpiCard, DataTable(with <640 card conversion +
  sortable headers + pager), FilterBar/FilterDrawer, StatusBadge families (§6),
  DoctorCard, SlotPicker + DateStrip, StarRating + Distribution, NotificationBell +
  Panel(aria-live), ChatbotWidget (pinned disclaimer + in-stream msg-emergency variant),
  EmergencyBlocks, ConfirmModal (Click|Typed|ReasonRequired + dependency counts),
  Stepper6/3 + WorkflowTracker variant, Feedback kit (Toast/InlineError/Spinner/
  RetryBanner/UnsavedBar), PdfButton (Idle|Loading|Ready|Disabled). Each ships all 4
  data states and respects G1–G8.
- Layouts: guest shell + 4 role shells (PLAN §5 Sprint 0.3) matching prototype shells
  (ink sidebar ≥992 → drawer <992; topbar with theme toggle, bell drawer, skip-link).
- Services: SettingsService (currency/locale/tz/date-format/revenue % cached+broadcast),
  CurrencyFormatter, ActivityLogService, NotificationService (+ Reverb channels),
  global error+retry handler (Sprint 0.4).
PART 1 — Guest & Public (PLAN Phase 1, sprints 1.1–1.8)
- Screens from design-out/prototypes/guest/: Landing (hero+search, emergency strip,
  featured-doctors data view), Directory (URL-synced filters, weekday picker, doctor grid
  ×4 states, pagination), DoctorPublicProfile (verified badge, 7-day leave-aware
  availability table ×4, approved-only reviews + distribution, guest booking gate),
  EmergencyInfo (one-tap tel: call cards ×4 with safe-default fallback, red-flag
  accordion by category, time-bounded banner, offline cache note), Login, Register
  (role-conditional license upload doctor/pharmacist, walk-in claim code patient, atomic
  tx + queue item), Forgot/Reset (30-min hashed tokens, session revocation),
  PendingApproval (3-stage stepper, Check Status poll ×4, rejection panel).
  Chatbot API with RedFlagEngine intercept BEFORE FAQ/AI retrieval; rate-limited.
PART 2 — Admin Core (PLAN Phase 2, sprints 2.1–2.8)
- Admin auth gate + idle timeout; Master Dashboard + System Overview (KPIs ×4 states,
  drill-downs) per prototypes/admin/01; User Management + role-change modal (02);
  Approval Queue with private-disk credential preview + reject-with-reason (03); Doctor
  Verification 3 decisions Approve&Verify / RequestResubmission / Revoke (03);
  Doctors CRUD + typed-confirm soft-archive with dependency counts (04); Patients CRUD
  + Patient Portal as-patient tabs (05).
PART 3 — Admin Advanced (PLAN Phase 3, sprints 3.1–3.10)
- Global appointments + cancel-on-behalf (06); Clinical & Billing record tabs + PDFs (07);
  Revenue % config with 0–100 CHECK, effective_from scheduling + impact preview (12, 08 §1);
  Reports 7 sections, each independently fetched with retry, CSV (formula-injection safe)
  + PDF export (08); Activity Log viewer w/ facets (09); FAQ manager + Review moderation
  (10); Emergency contacts/rules + symptom→specialty mapping console (11); Global System
  Settings incl. currency token preview + auto-approve + cancellation cutoff (12); Data
  Management: streamed CSV exports, two-step dry-run import with row report, backup
  snapshots, danger zone (password re-entry + typed confirm) (13); Admin Security: DB
  sessions + revoke-others (14).
PART 4 — Patient Portal (PLAN Phase 4, sprints 4.1–4.10, 4.11 UI hook)
- Patient shell + profile/password/settings incl. preferences & claim-code display (08);
  Allergies & Medications managers (severity chips, uniq checks) (07); Authenticated
  directory reuse (04.x); Booking flow doctor→date→slots(leave-aware)→reason→confirm with
  concurrency proof (slot-taken error modal + reload) (02); History status tabs with
  contextual actions (03); Prescriptions expandable rows + PDF (05); Bills totals/ID
  search/PDF (06); Notifications page/bell (01 drawer).
PART 5 — Patient AI (PLAN Phase 5, sprints 5.1–5.3)
- AI Symptom Checker wizard (disclaimer gate, consent, structured intake) → results
  (urgency, questions, DB-cross-validated specialty, top-3 doctors) with AI-down
  deterministic fallback + Retry; Emergency escalation modal (alertdialog, halt, hotlines,
  match reason, activity log) exactly per prototypes/patient/04 + SPEC §11/§12.
PART 6 — Doctor Portal (PLAN Phase 6, sprints 6.1–6.8)
- Verified-only login gate; Dashboard 4 KPIs + timeline + requests (01); Schedule builder
  (weekday blocks, slot durations, breaks, overlap validation) + Leave manager with
  conflict modal & affected-appointment counts (02); Requests tabs accept/reject-with-
  reason/complete (03); Patient list + Clinical Detail (allergy strip, 3-tab history)
  (04,05); Walk-in registration + claim-code receipt (atomic, rollback demo) (06);
  Medicine live-stock search + side-effect drawer + allergy/interaction checkers (07 §1).
PART 7 — Doctor Prescribing & AI (PLAN Phase 7, sprints 7.1–7.4)
- Prescription Builder: patient header, item rows w/ qty-vs-stock, live Allergy Conflict +
  Drug Interaction panels, override justification gate (textarea ≥20 chars + checkbox →
  enables Save), patient-facing vs pharmacy-facing notes, AI safety summary optional
  (fallback+Retry), Save + shared Rx PDF template (prototypes/doctor/07); prescription
  history/search/PDF (doctor variant of 05-reports style table); My Reviews with
  moderation-status visibility (08).
PART 8 — Pharmacist Portal (PLAN Phase 8, sprints 8.1–8.10)
- Approved-gate + settings; Dashboard 3 KPIs + oldest-5 + urgent stock (01); Pending Rx
  queue w/ stock flags (02); Pre-Dispense Safety Gate (allergy/interaction/expired-batch/
  insufficient-stock blocks, override sealed-record review modal, verification checks) (03);
  Dispense & Bill builder (live totals {{CURRENCY}}, discounts, payment methods) → THE
  7-step ACID transaction: row locks → validate → deduct → ledger → invoice(Rx→dispensed)
  → notify → discharge, with rollback tests; receipt + Invoice PDF (04); Inventory +
  Add/Edit medicine modal + Restock ledger modal (05); Stock alerts + Expiry tiers
  expired/≤30d/31–90d (06); Invoices + payment-status modal (refund reason) (07);
  6-stage Workflow Tracker horizontal/vertical with jump-to-stage + blocked variant (08).
PART 9 — Integration, Testing & Polish (PLAN Phase 9, sprints 9.1–9.6)
- E2E: register→verify→book→prescribe(safety gates)→dispense(ACID)→bill→discharge→review
  (Pest feature + at least one Dusk flow per role). Security: RBAC matrix, session
  revocation, CSRF/XSS/SQLi, CSV injection neutralization, upload validation, private
  disks, rate limits. Responsive QA matrix 360/640/768/1024/1440 against the prototypes
  (visual diff or checklist per screen). WCAG AA re-audit against DESIGN-RATIONALE §2 —
  every pair must pass with the shipped CSS. Perf: bounded/paginated queries, eager
  loading, indexes, cached settings. Docs: seed demo script + admin credentials sheet.
────────────────────────────────────────────────────────────────────────────────
QUALITY BAR PER PART (Definition of Done — from PLAN + DESIGN)
• Every server validation mirrored client-side; DB constraints back both.
• Ownership/role verified server-side; never trust client IDs.
• All 4 UI states demonstrable per data view + drawer/table conversions verified at
  360/768/1440.
• Currency via CurrencyFormatter; dates via user tz/format; role labels from DB.
• Activity log entry + notifications wired where PLAN specifies.
• Feature test happy path + ≥1 abuse/race path.
• Component files header-comment cites COMPONENT-SPEC §n and the prototype path it
  reproduces.

BEGIN NOW WITH PART 0 ONLY. Do not scaffold anything beyond PART 0's scope. End your
turn with the PART 0 COMPLETION REPORT and the question: "Reply 'continue' (with any
corrections) to proceed to PART 1." Then wait.
```

---
### How to use
1. Paste the code block above as the first message to your build agent.
2. After each `PART n COMPLETION REPORT`, reply `continue` (or corrections). Ten gates total.
3. The agent can always re-open: `design-out/COMPONENT-SPEC.md` (§ per component),
   `design-out/prototypes/<role>/…` (visual acceptance), `design-out/tokens.scss` (values),
   `design-out/DESIGN-RATIONALE.md` (decisions + AA table), and `DEVELOPMENT_PLAN.md`
   (sprints, schema, stack) — the prompt hard-links all of them.
