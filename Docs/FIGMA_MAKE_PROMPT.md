# MediPath — Figma Make UI/UX Design Prompts
**Project:** MediPath: AI-Powered Doctor Appointment and Medicine Safety System
**Companion doc:** `Docs/MEDIPATH_DEVELOPMENT_PLAN.md` (§5)

---

## How to use
1. Paste the **Master Prompt** into Figma Make first — it generates the design system, component library, and the core screens for all five roles.
2. Then run the **Follow-up Prompts** one at a time to generate the remaining role-specific screens and states.
3. Money is always shown as a token like `{{CURRENCY}} 1,500.00` — the design must **never** show a literal `$` sign (the real symbol comes from the Admin-managed global currency setting at runtime).
4. When the design is approved, export the color/spacing/type variables as design tokens and implement them as SCSS variables in the Laravel + Bootstrap build.

---

## 1. MASTER PROMPT (paste first)

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

---

## 2. FOLLOW-UP PROMPTS (run one at a time in Figma Make)

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

## 3. Design → Build handoff checklist
- [ ] Export color / spacing / typography variables → `resources/scss/_tokens.scss`
- [ ] Export component specs → Blade components (`doctor-card`, `data-table`, `skeleton`, `error-retry`, `status-badge`, `kpi-card`, `chatbot-widget`, `emergency-banner`, `star-rating`, `slot-picker`, `stepper-6stage`, `confirm-modal`, `filter-drawer`)
- [ ] Verify every screen exists at 360 / 768 / 1440 with table→card and drawer behaviors
- [ ] Confirm all four data states (skeleton / empty / error+Retry / success) per data view
- [ ] Confirm emergency red is used only for emergency elements; AA contrast report attached
