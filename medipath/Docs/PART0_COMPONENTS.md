# Part 0 — Global Component Library & Shells

Every component file carries a header citing its `COMPONENT-SPEC.md` section and the prototype it
reproduces. All data-bound components accept a `state` prop (`success | skeleton | empty | error`)
and every data region is wrapped in `DataView`, which also exposes an optional, keyboard-accessible
QA switcher.

## Component inventory

| # | Component (PLAN / SPEC §) | File | Prototype reference | States demonstrated |
|---|---|---|---|---|
| 1 | PublicNavbar (§1) | `Components/Navigation/PublicNavbar.tsx` | `prototypes/guest/01-landing.html`, all guest pages | Loading = auth chip slot; responsive drawer; scrolled shadow |
| 2 | RoleSidebar (§2) | `Components/Navigation/RoleSidebar.tsx` | `prototypes/{admin,doctor,pharmacist,patient}/01-*` | Counts skeleton/empty/error; fixed ≥992 / drawer <992 |
| 3 | KpiCard (§3) | `Components/Data/KpiCard.tsx` | `admin/01`, `doctor/01`, `patient/01`, `pharmacist/01` | Skeleton (CLS-sized), empty (`—`), error + Retry, success |
| 4 | DataTable (§4) | `Components/Data/DataTable.tsx` | `admin/02-users`, `09-activity`, `pharmacist/05-inventory`, `patient/03-appointments` | 5-row skeleton, empty + CTA, error + Retry, success + pager; <640 stacked cards with `data-label` |
| 5 | FilterBar/FilterDrawer (§5) | `Components/Data/FilterBar.tsx` | `guest/02-directory`, admin/pharmacy filters | Facet skeleton / facet error + Retry (inputs stay usable) / success; <768 right drawer |
| 6 | StatusBadge families (§6) | `Components/Data/StatusBadge.tsx` | all list screens | Skeleton pill / empty `—` / “Unknown status” / mapped tone |
| 7 | DoctorCard (§7) | `Components/Data/DoctorCard.tsx` | `guest/01-landing`, `02-directory`, `03-doctor-profile` | Card skeleton, details error + Retry, has-slots / fully-booked |
| 8 | SlotPicker + DateStrip (§8) | `Components/Data/SlotPicker.tsx` | `patient/02-booking` | Strip + 12-slot skeleton, “No open slots” empty, availability error + Retry, conflict (slot-taken) alert |
| 9 | StarRating + Distribution (§9) | `Components/Data/StarRating.tsx` | `guest/03-doctor-profile`, patient review form | Read/Input/Summary; skeleton bars, empty, error + Retry |
| 10 | NotificationBell + Panel (§10) | `Components/Feedback/NotificationBell.tsx` | `patient/01`, admin topbar | 3-row skeleton, “You’re all caught up”, error + Retry, Reverb live prepend |
| 11 | ChatbotWidget (§11) | `Components/AI/ChatbotWidget.tsx` | `guest/01-landing` FAB, patient chatbot | Typing skeleton, greeting/empty, AI-offline + Retry + deterministic fallback, in-stream emergency |
| 12 | EmergencyBlocks (§12) | `Components/Safety/EmergencyBlocks.tsx` | `guest/04-emergency` | Skeleton cards, safe-default fallback (never empty), error + Retry + last verified contacts |
| 13 | ConfirmModal (§13) | `Components/Overlay/ConfirmModal.tsx` | admin 04/12/13, pharmacist safety gate, patient escalation | Counts skeleton, 0-linked safe copy, counts error + Retry, submit error stays open; Click/Typed/Reason |
| 14 | Stepper6 / WorkflowTracker (§14) | `Components/Workflow/Stepper.tsx` | `pharmacist/08-workflow-tracker`, `guest/08-pending`, doctor workflow | Skeleton bars, “No workflow yet”, error + Retry, blocked stage with reason; jump-to-stage |
| 15 | Feedback kit (§15) | `Components/Feedback/{ToastStack,FeedbackKit,RetryBanner,EmptyState,AppErrorBoundary}.tsx` | every screen | Toast tones, persistent danger, inline error, spinners, retry banner with last-known time, unsaved bar |
| 16 | PdfButton (§16) | `Components/Data/PdfButton.tsx` | patient 05/06, pharmacist 04/07, doctor 05/07 | Idle / Loading / Ready (download) / Disabled + reason |
| 17 | Money (§17) | `Components/ui/Money.tsx` | all amounts | Server-formatted only; tabular numerals; no client formatting |

Supporting primitives: `DataView`, `EmptyState`, `RetryBanner`, `ThemeToggle`, `ToastProvider`,
`ThemeProvider`, `AppErrorBoundary`.

## Shells

| Shell | Layout | Verified at |
|---|---|---|
| Guest | `Layouts/GuestLayout.tsx` + PublicNavbar + footer | 360 / 768 / 1440 |
| Admin / Doctor / Pharmacy / Patient | `Layouts/RoleLayout.tsx` + RoleSidebar + bell | 360 / 768 / 1440 (Dusk) |

The role sidebar is **one DOM instance**: sticky 264 px column ≥992, focus-trapped slide-over
`min(340px, 86vw)` below 992. The notification panel is a popover ≥768 and a right drawer below 768.
The topbar hides the avatar (<640) and the Register CTA (<992) to keep 44 px targets on one row;
auth actions remain in the drawer.

## How to demonstrate the 4 states

1. Open `/dev/ui` (local only).
2. Each dashed `Data view` frame has a **Success / Skeleton / Empty / Error + Retry** switch.
3. Or load `/dev/ui?state=skeleton` (also `empty` / `error`) — the URL propagates into every view’s
   initial state via the shared `ui.initialState` prop.
4. Error states keep filters (the page owns URL state) and display the last-known timestamp; Retry
   re-fetches the same query.
5. Exact-width QA: `/dev/frames/360?path=/dev/ui`, `.../768`, `.../1440` render the real app in an
   iframe at the exact CSS width (media queries execute faithfully).
   Dusk writes screenshots to `tests/Browser/screenshots/`.

## Contract checks enforced by tests

* `tests/Feature/DesignContractTest.php`: token values, per-file SPEC citation, no currency glyph in
  components/pages, no hardcoded role labels in components, emergency-class scope ledger, 4-state
  contract, touch/sidebar/navbar tokens.
* `tests/Feature/RoleGateTest.php`: role matrix + JSON abuse path.
* `tests/Feature/DatabaseConstraintTest.php`: stock, rating, revenue, discount, role checks, slot race.
* `tests/Feature/CurrencyAndSettingsTest.php`: ৳ formatting, symbol from settings, negative
  parentheses, cache invalidation, broadcast, revenue effective dating.
* `tests/Feature/NotificationTest.php`: ownership abuse, unread counts, mark-all isolation.
* `tests/Feature/SeederTest.php`: seed graph shape + invariants + idempotency.
* `tests/Browser/RoleShellResponsiveTest.php`: 360/768/1440 shell, drawer, table→card threshold,
  theme wiring, mobile bell/filter drawers.