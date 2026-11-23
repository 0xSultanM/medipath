# MediPath — Figma-SKILL-Only Design Prompt (NO Figma MCP server calls)
**Purpose:** Paste this prompt into any model/session in OpenCode that has your imported Figma skill. The model will **load and apply the Figma skill's methodology locally** (tokens, auto-layout thinking, components/variants, accessibility, responsive frames) but is **strictly forbidden from triggering any Figma MCP server API call**. All design artifacts are produced as local files (tokens, component specs, responsive HTML/CSS prototypes, rationale doc).
**Companion docs:** `Docs/MEDIPATH_DEVELOPMENT_PLAN.md`, `Docs/FIGMA_MAKE_PROMPT.md`, `Docs/FIGMA_MAKE_PROMPT_PARTS.md`

---

## HOW TO USE
1. Open a new session with a model that has the Figma skill imported.
2. Paste the entire prompt block below as the first message.
3. The model will pause after each role's screen batch — reply `continue` (or `continue all`) to proceed.

---

## THE PROMPT (copy everything inside the fence)

```
ROLE & CONTEXT
You are the senior product designer + front-end prototyper for "MediPath: AI-Powered
Doctor Appointment and Medicine Safety System": a responsive clinic web platform with
five roles - Guest (public), Patient, Doctor, Pharmacist, Admin. Core flows: verified-doctor
discovery & booking with leave-aware slots; AI symptom checker with deterministic emergency
red-flag escalation; prescriptions with allergy/drug-interaction safety gates; pharmacy
dispensing coupled to stock deduction + billing; admin governance, verification, analytics.
If the files Docs/MEDIPATH_DEVELOPMENT_PLAN.md and Docs/FIGMA_MAKE_PROMPT.md exist in the
workspace, read them before designing.

SKILL RULES - STRICT
1. FIRST load the imported Figma skill available in this environment (via the skill tool,
   e.g. the figma-use / figma design skills) and extract its methodology: variables & tokens,
   auto-layout, components & variants, naming conventions, accessibility, responsive frames,
   design-system thinking.
2. NEVER call the Figma MCP server or any Figma API/tool. Forbidden (non-exhaustive):
   use_figma, create_session, create_new_file, generate_figma_design, get_design_context,
   get_screenshot, get_metadata, get_figjam, upload_assets, search_design_system,
   get_libraries, add_code_connect_map, any tools.figma.* / mcp figma calls, and any HTTP
   request to figma.com. Zero network writes to Figma, zero file sessions.
3. If a step seems to require Figma MCP, perform it locally instead (files under
   ./design-out/). Never claim anything was created inside Figma.
4. Use the skill purely as a knowledge base and quality bar; in the rationale doc, cite
   which skill principle each major design decision follows.

DELIVERABLES (local files under ./design-out/)
A) tokens.scss + tokens.json - colors (light + dark), type scale, spacing, radii, shadows,
   breakpoints.
B) COMPONENT-SPEC.md - global library: public navbar, role sidebars, KPI card,
   DataTable->card conversion, FilterBar/FilterDrawer, StatusBadge sets, DoctorCard,
   SlotPicker, StarRating, NotificationBell, ChatbotWidget (pinned non-dismissible
   disclaimer + in-stream emergency banner), EmergencyBlocks, ConfirmModal (typed-confirm
   + dependency counts), Stepper6, feedback set (toast, inline error, spinner, Retry
   banner), PdfButton. For EACH: layout rules, auto-layout/stacking behavior, variants, and
   the 4 data states (skeleton, empty, error+Retry, success).
C) prototypes/ - high-fidelity responsive HTML+CSS (Bootstrap 5 grid permitted; no JS
   frameworks) for every screen listed below, each rendered at 360 / 768 / 1440 px,
   mobile-first, with all 4 states demonstrable per data view.
D) DESIGN-RATIONALE.md - decision-to-skill-principle mapping + WCAG 2.1 AA self-audit table.

SCREENS
Guest: Landing, Doctor Directory (+filters), Doctor Public Profile (7-day availability,
approved reviews), Emergency Info, Login, Register (conditional license upload / walk-in
claim code), Forgot-Reset, Pending Approval (3-stage progress).
Patient: Dashboard, Booking (doctor -> date -> slots -> reason), Appointment History
(status tabs + contextual actions), AI Symptom Checker (intake -> results -> emergency
escalation modal), My Prescriptions (+PDF button), My Bills (ID search + PDF), Allergies &
Medications managers, Profile / Password / Settings.
Doctor: Dashboard (4 KPIs), Schedule Builder + Leave manager (conflict modal), Appointment
Requests (accept / reject-with-reason / complete), Patient List + Clinical Detail (allergy
alert strip, 3-tab history), Walk-In Registration (claim code receipt), Prescription
Builder (live stock search, side-effect drawer, allergy + interaction warning panels,
override justification, patient-facing vs pharmacy-facing notes), My Reviews.
Pharmacist: Dashboard (3 KPIs + queues), Pending Prescriptions queue, Pre-Dispense Safety
Gate, Dispense & Bill builder (live totals) + receipt, Inventory + Add Medicine + Restock,
Stock Alerts, Expiry Alerts tiers, Invoices + payment status modal, Workflow Tracker
(6 stages).
Admin: Master Dashboard + System Overview, User Management + role change, Approval Queue,
Doctor Verification (3 decisions), Doctors/Patients CRUD + soft-archive modals, Patient
Portal, Appointments oversight, Clinical & Billing record tabs, Revenue % config, Reports
(7 sections + CSV/PDF export), Activity Log, FAQ manager, Review moderation, Clinical
Safety console (2 tabs), System Settings, Data Management, Admin Security.

DESIGN SYSTEM - MANDATORY
Primary teal #0E7C86; ink navy #0B2545; bg #F6F9FB; surface #FFFFFF; border #E2E8F0;
success #16A34A; warning #D97706; danger #DC2626; info #2563EB; emergency red #B91C1C
(emergency elements ONLY). Light + dark themes with AA contrast in both. Inter or Manrope;
8pt spacing grid; radius 12 card / 8 input / 999 pill. Money ALWAYS shown as {{CURRENCY}}
token - never a literal "$". Below 640px tables become stacked cards; below 768px navbars
and sidebars become slide-over drawers and filters move into a drawer; doctor grid 1/2/3
columns; touch targets >=44x44; visible focus rings; skip-to-content link; aria-live for
chat and notifications; role labels and specialties never hardcoded.

WORKFLOW
1. Load the skill; reply with <=10 bullets of the principles you will apply.
2. Build deliverables A -> B -> C role-by-role in order: Guest, Patient, Doctor,
   Pharmacist, Admin. Pause after each role for my review unless I say "continue all".
3. After every screen batch run this SELF-CHECK and print the result line:
   [360/768/1440 present] [4 states present] [AA contrast] [currency token only]
   [no hardcoded roles/labels] [emergency red scoped] [NO Figma MCP calls made]
4. Finish with D plus a short open-questions list.
Acceptance: I can open every prototype at 3 widths, toggle all 4 states, and trace each
component to a token and to a cited skill principle.
```

---

## Guardrail reminder (for you, not the model)
If the model still attempts a `tools.figma.*` call, reply: *"MCP forbidden — produce the artifact locally per SKILL RULES 2–3."* The skill content itself (loaded via the skill tool) is local instruction text and does not require the MCP server.
