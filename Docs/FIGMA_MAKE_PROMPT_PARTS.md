# MediPath — Figma Make Prompt, Split into ≤2000-Character Parts
**Why:** Figma Make accepts a maximum of **2000 characters per prompt**. This file splits the full design context into **10 sequential parts + a BEGIN command**. Send them **in order**; Figma Make stores each part and only starts designing after `BEGIN DESIGN`, so it holds the full context before creating anything.
**Protocol rule built into every part:** after each part Figma Make replies only `ACK PART n/10 stored.` — no design output until BEGIN.
**Companion docs:** `Docs/FIGMA_MAKE_PROMPT.md` (single long version), `Docs/MEDIPATH_DEVELOPMENT_PLAN.md`.

---

## HOW TO USE
1. Paste **PART 0** first, wait for the ACK.
2. Paste **PART 1 → PART 10** in order, waiting for each ACK.
3. Paste **BEGIN DESIGN** (bottom of this file).
4. Figma Make will generate page by page and pause; reply `continue` (or a specific instruction) after each page.
5. If context is lost mid-session, reply: `resend part N` and paste that part again.

---

## PART 0/10 — Protocol & project identity

```
Figma Make context-loading protocol for "MediPath: AI-Powered Doctor Appointment and
Medicine Safety System". I will send 10 short context parts (PART 1..10). After each part
reply ONLY: "ACK PART n/10 stored." Do NOT design, do NOT generate screens, do NOT ask
questions until I send "BEGIN DESIGN". Keep every part in memory; full design context =
union of all parts. If you lose context, ask me to resend a specific part.
Project: responsive clinic web app, 5 roles: Guest (public), Patient, Doctor, Pharmacist,
Admin. Core flows: verified-doctor discovery & booking with leave-aware slots; AI symptom
checker with deterministic emergency red-flag escalation; prescriptions with allergy and
drug-interaction safety gates; pharmacy dispensing coupled to stock deduction and billing;
admin governance, credential verification, reports and analytics.
Global rules preview: money always shown as {{CURRENCY}} token, never "$"; every data view
has 4 states (skeleton, empty, error+Retry, success); mobile-first frames at 360 / 768 /
1440 for every screen; WCAG 2.1 AA. Store this part; reply per protocol.
```

## PART 1/10 — Design system: color, type, spacing

```
PART 1/10 DESIGN SYSTEM. Style: clean clinical healthcare SaaS; calm, trustworthy, high
legibility. Colors: primary teal #0E7C86 (buttons, links, active nav); ink navy #0B2545
(headings, sidebar bg); page bg #F6F9FB; surface #FFFFFF; border #E2E8F0; success #16A34A;
warning #D97706; danger #DC2626; info #2563EB; emergency red #B91C1C reserved ONLY for
emergency banners, red-flag alerts and emergency call CTAs. Dark theme: bg #0F172A,
surface #1E293B, border #334155, text #E2E8F0, accents lightened to keep AA contrast.
Typography: Inter (fallback Manrope): H1 32/40 bold, H2 24/32 semibold, H3 20/28 semibold,
body 16/24 regular, caption 13/18 medium; tabular numerals for money, IDs and dates.
Spacing: 8pt grid (8/16/24/32/48). Radii: card 12, input 8, chip/pill 999. Shadows: soft
elevation only (0 1 2 rgba(16,24,40,.06), 0 8 24 rgba(16,24,40,.08)). Icons: 20px outline
set, stroke 1.75. Create Figma variable collections: color/light, color/dark, spacing,
radius, typography. Store this part; reply per protocol.
```

## PART 2/10 — Responsive & accessibility rules

```
PART 2/10 RESPONSIVE + ACCESSIBILITY RULES. Generate EVERY screen in 3 frames: Mobile
360x780, Tablet 768x1024, Desktop 1440x900, all auto-layout. Mobile-first rules: below
640px all data tables convert to stacked cards (label:value rows) and doctor grid is 1
column; 640-767 doctor grid 1-2 cols; 768-991 doctor grid 2 cols and sidebars become
offcanvas slide-over drawers opened by a hamburger; 992+ doctor grid 3 cols with persistent
260px left sidebar + top header. Filter panels: inline bar on desktop, slide-over drawer
below 768 with the search box pinned at top. Touch targets >=44x44. WCAG 2.1 AA: contrast
>=4.5:1 body, >=3:1 large text and UI; visible 2px focus rings; skip-to-content link;
aria-live polite for chat and notification updates; form errors inline under fields with
icon + text, never color alone; status badges always pair text with color. Keyboard: modals
trap focus, drawers close on Esc. Money/numbers right-aligned tabular. Zero layout shift:
skeletons match final element sizes exactly. Store this part; reply per protocol.
```

## PART 3/10 — Component library A (1–8)

```
PART 3/10 COMPONENT LIBRARY A. Every component: named, auto-layout, variants documented,
and designed in 4 states (skeleton, empty, error-with-Retry-button, success).
1 Public navbar: MediPath logo; links Find Doctors, Emergency Info (high-contrast red
outline pill), Login, Register (primary); below 768 becomes slide-over menu.
2 Role sidebar (Patient/Doctor/Pharmacy/Admin variants): icon+label items, active pill,
section groups, unread bell badge in header, collapses to drawer on mobile.
3 KPI card: label, big tabular value, delta chip, sparkline slot; shimmer skeleton variant.
4 DataTable: sortable headers, 1px row borders, pagination footer; below 640 becomes
stacked cards.
5 FilterBar + FilterDrawer: debounced search, specialty select, weekday availability
select, Available Today toggle, min-rating 0-5, sort select, removable active-filter tags,
Clear All button.
6 StatusBadge sets: appointment pending/accepted/rejected/cancelled/completed; stock
healthy/low/critical/out; expiry ok/upcoming/critical/expired; payment paid/pending/
refunded; account pending/approved/rejected/suspended/archived.
7 DoctorCard: avatar or initials fallback, name, specialty chip, years experience, stars +
review count, {{CURRENCY}} fee, next-available line, action slot (View Profile / Book).
8 SlotPicker: 7-day date strip + time chip grid; disabled chips for past/leave/booked with
tooltip reasons. Store this part; reply per protocol.
```

## PART 4/10 — Component library B (9–16)

```
PART 4/10 COMPONENT LIBRARY B. Same rules: named, auto-layout, 4 states each.
9 StarRating: keyboard-navigable 1-5 input + display variant + 5-to-1 distribution bars.
10 NotificationBell: unread count badge, dropdown preview list, link to full paginated
page; items show category icon, message, relative + absolute time, read/unread dot,
Mark-all-read action.
11 ChatbotWidget: floating FAB; open panel (full-height drawer on mobile) with PINNED
non-dismissible disclaimer header ("general clinic info only, not a diagnosis, never
replaces a licensed doctor"); message stream; suggestion chips; input bar; plus an
in-stream EMERGENCY ALERT banner variant (red #B91C1C, white text, one-tap call buttons,
link to Emergency page).
12 EmergencyBlocks: large one-tap tel: cards, categorized red-flag accordion (cardiac,
respiratory, stroke, bleeding, anaphylaxis, pediatric), time-bounded clinic alert banner,
offline static-fallback notice.
13 ConfirmModal: summary body + optional reason field; danger variant requires typing the
record name and shows dependency counts (e.g. 12 appointments - 34 prescriptions).
14 Stepper6: Registration > Prescription > Stock Check > Dispense > Billing > Discharged;
horizontal desktop, vertical mobile, current step highlighted, click-through jump action.
15 Feedback set: toasts, inline field errors, saving spinner, unsaved-changes warning,
Retry banner.
16 PdfButton: label + loading spinner + success tick (Prescription PDF / Invoice PDF).
Store this part; reply per protocol.
```

## PART 5/10 — Guest screens

```
PART 5/10 GUEST SCREENS.
G1 Landing: hero headline + doctor search bar, emergency strip link, 3 value props
(verified doctors, AI safety checks, pharmacy in one loop), featured specialty chips,
footer.
G2 Doctor Directory: FilterBar top (search pinned on mobile), doctor grid 1/2/3 cols,
pagination, skeleton cards, empty state, error+Retry.
G3 Doctor Public Profile: header (avatar, name, verified badge, specialty, experience,
rating summary, {{CURRENCY}} fee), read-only 7-day availability table (no patient
identities), paginated approved reviews with privacy labels like "Rahim A.", sticky
"Sign In to Book" CTA carrying a safe return path.
G4 Emergency Info: safety directive banner first, one-tap call cards, red-flag accordion,
active clinic alerts, offline fallback note.
G5 Login: email, password + visibility toggle, forgot link, rate-limit notice state.
G6 Register: role radio Patient/Doctor/Pharmacist (never Admin); conditional blocks for
Doctor/Pharmacist (license number, authority, experience years, specialty select,
credential upload) and Patient (optional age/gender/walk-in claim code); inline
validations.
G7 Forgot/Reset: two steps + expired-link state with request-new button.
G8 Pending Approval: 3-stage progress (Submitted > Admin Review > Activation), masked
license, check-status button, approved banner, rejection reason panel, sign out.
Store this part; reply per protocol.
```

## PART 6/10 — Patient screens

```
PART 6/10 PATIENT SCREENS (sidebar: Dashboard, Find Doctors, My Appointments, AI Symptom
Checker, My Prescriptions, My Bills, Allergies & Medicines, Reviews, Notifications,
Settings).
P1 Dashboard: KPI row (next appointment, active prescriptions, unread), next-appointment
card with cancel/reschedule, quick actions.
P2 Booking: step 1 doctor select (reuse DoctorCard grid), step 2 date strip + SlotPicker
(leave/booked disabled), step 3 reason + confirm summary; slot-taken error state.
P3 Appointment History: tabs All/Pending/Accepted/Rejected/Cancelled/Completed; rows with
status badge, doctor, date-time in user tz/format, contextual actions (Cancel, Reschedule,
Leave Review, View Prescription); empty state CTA "Book First Appointment".
P4 AI Symptom Checker: permanent disclaimer card; intake (symptoms multi-select, body
region, description, duration, severity mild/moderate/severe, onset sudden/gradual, consent
toggle for age/gender/allergies/meds); results (urgency guidance, non-diagnostic
explanations, questions to ask, primary + up to 2 secondary specialties, top-3 verified
doctors with Book buttons, Browse Specialists); EMERGENCY variant: full-screen red alert
modal halting flow, shows triggered red-flag text, call buttons, ER directions.
P5 My Prescriptions: rows (ID, date, doctor + specialty, status) + expandable medicine
table (dose/schedule/duration/notes) + Download PDF.
P6 My Bills: totals card (billed/paid/outstanding in {{CURRENCY}}), invoice ID search,
rows + detail drawer + Download PDF.
P7 Allergies & Medications: two managers, severity chips, reaction notes, add/edit/remove,
reminder note. P8 Profile / Password / Settings (theme, language, tz, date format,
notification toggles). Store this part; reply per protocol.
```

## PART 7/10 — Doctor screens

```
PART 7/10 DOCTOR SCREENS (sidebar: Dashboard, Schedule, Appointment Requests, Patients,
Medicines, Prescriptions, Reviews, Notifications, Settings).
D1 Dashboard: 4 KPIs (today's patients, prescriptions today/this month, pending requests,
avg rating); today's timeline with Open Chart / Write Prescription buttons; pending
requests panel with Accept/Reject shortcuts.
D2 Schedule Builder: 7 weekday cards, working blocks (start-end), slot duration 15/20/30,
break window; overlap validation errors; Leave manager (date ranges) + conflict warning
modal listing affected appointments with keep-honored vs cancel-and-notify choice.
D3 Appointment Requests: tabs Pending/Today/Upcoming/Completed/Cancelled-Rejected; rows:
patient name, age/gender, patient ID, slot in doctor tz, reason, status; inline Accept,
Reject-with-reason modal, Mark Completed; link to patient chart.
D4 Patients: search (name/ID/phone/condition) + toggles (Recent, Seen Today, Has
Allergies); list rows with allergy alert indicator; Clinical Detail: demographics,
HIGH-VISIBILITY allergy strip, current meds, buttons History / New Prescription; History
tabs Prescriptions/Appointments/Billing (read-only).
D5 Walk-In Registration: form + duplicate-check warning + generated claim code receipt.
D6 Prescription Builder: patient header; live-stock medicine search (stock chip, expiry
warn, side-effect drawer); item rows dosage/schedule/duration/qty; live safety panels
Allergy Conflict (substance, severity, reaction) and Drug Interaction (Minor..
Contraindicated + explanation) with override justification for severe/major; notes split
patient-facing vs pharmacy-facing; Save + Download PDF.
D7 My Reviews: summary + distribution + paginated list, star filter, sort newest/lowest.
Store this part; reply per protocol.
```

## PART 8/10 — Pharmacy screens

```
PART 8/10 PHARMACY SCREENS (sidebar: Dashboard, Pending Prescriptions, Medicine Inventory,
Stock & Expiry Alerts, Billing & Invoices, Workflow Tracker, Notifications, Settings).
H1 Dashboard: 3 KPIs (pending prescriptions, dispensed today + {{CURRENCY}} value,
low/critical + expiring count); queues: oldest-5 pending with Jump to Dispense; urgent
stock/expiry with Restock.
H2 Pending Prescriptions: paginated queue; rows: Rx ID, date, patient name + ID, doctor +
specialty, medicine lines with required qty vs live stock flag (insufficient/expired
blocks), pharmacy notes; Review & Dispense CTA.
H3 Pre-Dispense Safety Gate: stacked warning panels (allergy conflict, interaction, doctor
override note, expired batch BLOCK, insufficient stock BLOCK) + pharmacist verification
checkbox + optional note; Dispense disabled until gate passes.
H4 Dispense & Bill: itemized rows (live unit price {{CURRENCY}}, live stock, qty with
over-stock block), discount %/fixed validated, payment method, status Paid/Pending, live
totals preview; commit button "Dispense + Bill + Discharge"; success receipt + Invoice PDF.
H5 Inventory: table (name, category, price, stock, min threshold, expiry, status badge) +
Restock/Edit/Archive; Add Medicine form (validated fields incl. side effects); Restock
modal (qty, batch, expiry, supplier note).
H6 Stock Alerts: sorted shortage list with deficit + Restock. H7 Expiry Alerts: tabs
Expired / Critical <=30d / Upcoming 31-90d with quarantine/restock actions.
H8 Invoices: directory + payment status modal (paid confirm / refund reason) + PDF.
H9 Workflow Tracker: Stepper6 per encounter, search by patient/Rx ID, stage filter, jump
actions. Store this part; reply per protocol.
```

## PART 9/10 — Admin screens

```
PART 9/10 ADMIN SCREENS (sidebar: Dashboard & Overview, Approvals & Doctor Verification,
Doctors, Patients & Portal, Appointments & Records, Revenue & Analytics, FAQs & Reviews,
Emergency & AI Rules, Settings, Data Management, Security).
A1 Master Dashboard: 5 KPIs (active patients, verified doctors, prescriptions issued,
active medicines, clinic revenue) + commission revenue via stored %; toggles Today/7d/30d/
Year. A2 System Overview: live indicators (doctors on duty, active patients, pending
dispense, low/critical stock + expiring) + badge counts pending signups/unverified
doctors; drill-down links. A3 Users: directory + filters; suspend/
reactivate; role-change modal with guardrails; archive confirm with dependency counts.
A4 Approval Queue: pending cards with license details + secure doc preview; Approve /
Reject-with-reason. A5 Doctor Verification: queue + 3 decisions (Approve & Verify, Request
Resubmission, Revoke). A6 Doctors/Patients CRUD + soft-archive modals; Patient Portal:
search + Rx/billing history tabs. A7 Appointments oversight: filters + cancel/reassign
with notify. A8 Records tabs: Prescriptions / Inventory / Invoices with PDF buttons.
A9 Revenue % config (0-100 validated). A10 Reports: 7 sections (revenue, patient
growth, Rx completion, invoice collection, stock health/expiry risk, appointment
conversion, top-doctor leaderboard), sortable tables + charts + CSV/PDF export. A11 Activity Log: read-only facet-filtered viewer. A12 FAQ manager + Review
moderation queue. A13 Safety console: tab1 emergency contacts/alert banners/red-flag
rules; tab2 symptom-to-specialty mapping. A14 System Settings: clinic identity, currency/
locale, tz, date format, auto-approve toggle, cancellation cutoff. A15 Data Management:
CSV export, dry-run import preview + error report, backup snapshot, danger zone (password
re-entry + typed phrase). A16 Admin Security: profile, password, active sessions + revoke
others. Store this part; reply per protocol.
```

## PART 10/10 — States, themes, organization, generation order

```
PART 10/10 GLOBAL STATES, THEMES, OUTPUT. Every data view ships 4 states: skeleton
(layout-matched, zero shift), empty (friendly + CTA), error (message + working Retry
button), success. Modals: focus trap, Esc close, typed-confirm danger variant. Toasts for
saves; inline errors under fields. Dark mode via variable swap, re-check AA. Emergency red
only for emergency elements. Money always {{CURRENCY}} token; dates/times per user or
system timezone + format settings; role labels and specialties from database, never
hardcoded. Organization: Figma pages 00 Tokens & Components, 01 Guest, 02 Patient,
03 Doctor, 04 Pharmacy, 05 Admin, 06 States & Patterns; components in a published library
with variants; auto-layout everywhere; realistic sample medical data (no lorem ipsum).
WHEN I SEND "BEGIN DESIGN": first restate the stored context in <=10 bullets, then generate
in this order: tokens + components (Parts 1-4) -> Guest (5) -> Patient (6) -> Doctor (7) ->
Pharmacy (8) -> Admin (9) -> states/dark pass (10), pausing after each page for my review.
Reply now per protocol.
```

---

## BEGIN DESIGN (send last)

```
BEGIN DESIGN. Context parts 1-10 are stored. Restate the context in <=10 bullets, then
generate page "00 Tokens & Components" at 360/768/1440 now. Pause when done.
```

**Per-page continue prompts (after each pause):**
- `continue` — generate the next page in the order defined in PART 10.
- Or target one: `generate page 03 Doctor now` / `apply dark mode to page 02 Patient and re-check AA contrast`.
