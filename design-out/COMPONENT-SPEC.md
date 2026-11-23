# MediPath — Global Component Library Specification
**Version 1.0 · 23 Nov 2026 · Companion to `tokens.json` / `tokens.scss` / `prototypes/assets/medi.css`**

> Methodology note: this spec applies the imported Figma skill's *design-system concepts*
> (variable collections with modes, auto-layout hug/fill/fixed, variant properties,
> scoped tokens, inspect-before-create, evidence-based validation) as a **knowledge base
> only**. No Figma MCP/API was called — everything lives in this repo under `design-out/`.
> Skill principle citations: `[P#]` keys map to the bullets in DESIGN-RATIONALE.md §0.

---

## 0. Global conventions

| Concern | Convention |
|---|---|
| Naming (files) | `kebab-case`: `doctor-card`, `filter-bar`, `slot-picker` (mirrors Blade component names in plan §5.2 handoff checklist) |
| Naming (Figma-style components) | `Category / Name` — `Actions / Button`, `Data / Doctor Card`, `Feedback / Toast`, `Safety / Emergency Banner` |
| Variants | Figma variant-property syntax `Prop=Value` comma-joined: `State=Success, Tone=Warning, Size=MD` |
| Tokens | Every color/space/radius/shadow/type value binds a `--mp-*` variable. **No literal hex/px in components** [P1]. Theme = one collection, two modes (Light/Dark) — mirrors variable modes [P1] |
| Currency | Money renders `{{CURRENCY}} 1,500.00` — never `$`. Tabular numerals (`font-variant-numeric: tabular-nums`) on all money/IDs/dates |
| Roles & labels | Role labels ("Patient", "Pharmacist") and specialty names come from DB (`users.role`, `specialties.name`) and enter components via slots/props; prototypes mark such text `data-src="db:…"` [P10] |
| Emergency red | `#B91C1C` (`.badge-emergency`, `.btn-emergency`, `.msg-emergency`, `.emg-banner`, `.call-card`, `.alert-emergency`) may appear **only inside emergency UI regions**. Routine errors/destructive = danger `#DC2626` [P9] |
| Touch & focus | Interactive elements `min-height/width: 44px` (`--mp-touch`); every focusable gets a visible `--mp-focus-ring` outline; skip-link at top of every page; `aria-live` for chat & notification regions |
| Responsive thresholds | <640: tables→stacked cards, doctor grid 1-col · 640–767: 2-col · 768+: navbar/sidebar→slide-over drawers below 768, filters→drawer · ≥992: 3-col + fixed role sidebar · design canvas 1440 |
| Auto-layout mapping [P2] | Flex/grid everywhere: `HUG`= `width:auto` inline content; `FILL`= `flex:1 / stretch`; `FIXED`= `--mp-*` sizing tokens. Gap values only from spacing tokens. "Stack related children in auto-layout, never absolute-position siblings" → no absolute positioning except overlays/backdrops |
| Data states | Every data-bound view ships 4 states behind one `State=` variant property: `Skeleton, Empty, Error(+Retry), Success`. Skeletons match final layout metrics (no CLS) [P5] |
| Build handoff | Each component = one Blade component in `resources/views/components/` (plan §3.1) consuming the same tokens; SCSS partials import `_tokens.scss` |

Prototype harness: each data view is wrapped in `.dataview` with a `.dv-switch` radio group
(CSS `:has()` — no JS required). Screens additionally expose theme toggle (`#mp-theme`).

---

## 1. Public Navbar — `Navigation / Public Navbar`
**Purpose:** unauthenticated top bar: brand, *Find Doctors*, *Emergency Info* (permanent high-contrast), *Login/Register*.
- **Layout:** sticky header, height token `--mp-navbar-h` (64px). Row: brand HUG | links HUG (end-aligned) | auth cluster HUG (mla). `State=Scrolled` adds `--mp-shadow-md`.
- **Auto-layout:** horizontal, align center, gap `space-sm`→`space-lg`; padding-inline `space-base` (≥640: `space-lg`).
- **Variants:** `Links=Guest|Authed, Emergency=Visible (always), Responsive=Desktop(≥768)|MobileDrawer(<768)`.
- **Responsive:** <768px links collapse into `#drawer-nav` slide-over (from left, `--mp-z-drawer`), burger button 44×44, keyboard-accessible (checkbox label + Esc + focus ring; backdrop click closes).
- **Data states:** nav itself is static — N/A; auth cluster shows `State=Loading` skeleton for user chip in role shells.
- **A11y:** `<nav aria-label="Primary">`; emergency link uses scoped `.is-emergency` (red) because it *is* an emergency affordance; current page link `aria-current="page"`.
- **Tokens:** surface, border, ink, primary, emergency, navbar-h, radius-input, touch.

## 2. Role Sidebar — `Navigation / Sidebar (Role=Patient|Doctor|Pharmacy|Admin)`
**Purpose:** authenticated workspace nav; ink-navy column with icon+label items, active state, unread counts.
- **Layout:** 264px fixed (`--mp-sidebar-w`), full-height sticky column; sections: brand, role badge (`data-src="db:users.role.label"`), nav groups, footer (profile + logout). Items: row, gap `space-md`, min-height 44.
- **Auto-layout:** vertical; items FILL width; label HUG; counter chip HUG (mla). Group headings HUG top-aligned.
- **Variants:** `Role=Patient|Doctor|Pharmacy|Admin, ItemState=Default|Hover|Active|Disabled, Responsive=Fixed(≥992)|Drawer(<992)`.
- **Responsive:** <992px sidebar becomes the same left slide-over drawer (`#drawer-nav`) inside the role topbar; drawer keeps focus order (nav element moves, no DOM duplication).
- **Data states:** drawer badge count = data-bound: skeleton pill / empty (no badge) / error (badge hidden + `aria-live` polite text "counts unavailable") / success.
- **A11y:** `aria-label="Sidebar"`; active item `aria-current="page"`; counts `aria-label="3 unread requests"` (never color-only).
- **Tokens:** sidebar-bg/hover/active/text, radius-input, space tokens.

## 3. KPI Card — `Data / KPI Card`
**Purpose:** dashboard metric: label, value, delta, sparkline slot.
- **Layout:** vertical stack gap `space-sm`; value = `kpi-value` 28/34 bold tabular; foot row: delta (HUG) + sparkline slot (FIXED 88×32).
- **Auto-layout:** card FIXED min-width 0 (grid child FILL); `is-alert`/`is-bad` tone = left 4px accent bar (non-text status is paired with the delta arrow + tone name).
- **Variants:** `Tone=Neutral|Success|Warning|Danger, Trend=Up|Down|Flat, Sparkline=On|Off`.
- **Data states:** Skeleton = label line + 28px block + bar block (matches final metrics); Empty = `—` value + "No data for range"; Error+Retry = "Couldn't load KPI" + `.btn-ghost` Retry (server-side KPI aggregation is a separate fetch per KPI so one failure doesn't sink the row); Success = live value.
- **A11y:** `role="status"` region? No — KPIs are static per fetch; wrap in `<figure>` + `<figcaption>`; delta includes ▲/▼ glyph + text "+12% vs last month".
- **Tokens:** surface, border, radius-card, success-text/danger-text deltas, chart-1 spark.

## 4. DataTable → Card conversion — `Data / Table`
**Purpose:** canonical list view; sortable headers, pagination bar.
- **Layout:** wrapper card (radius-card, overflow hidden): header row (title + tools HUG) / table / pager. Cells 12×16 padding, `th` uppercase 12px muted.
- **Auto-layout:** rows stack gap 0; cell content row align-center.
- **Responsive:** **<640px table converts to stacked cards** — `thead` hidden; each `tr` becomes a bordered card; each `td` renders `data-label` pseudo-header (left) + value (right, `num` tabular right-aligned). Action cell full-width row of 44px buttons. ≥640: true table.
- **Variants:** `Density=Comfortable|Compact, Sortable=Yes|No, RowClick=On|Off, Pagination=On|Off`.
- **Data states:** Skeleton = 5 `sk` rows with real column widths (CLS-safe); Empty = `.empty` glyph + CTA (e.g., "Reset filters"/"Register first patient"); Error+Retry = `.retry-banner` with `role="alert"` + Retry (re-fetch same page/filters); Success = rows + server-paginated pager (page count from meta, aria-current page).
- **A11y:** `<table>` with `<caption class="sr-only">`; sort buttons `aria-sort="ascending"`; pager links labeled; row badges repeat state *text* — never color-only.
- **Tokens:** surface, border, surface-muted header, radius-card, sm text scale.

## 5. FilterBar / FilterDrawer — `Input / Filter Bar`
**Purpose:** directory/search controls: debounced search, specialty select, weekday availability, "Available Today" toggle, min-rating slider, sort, removable tags, Clear All.
- **Layout:** desktop grid (`2fr 1.2fr 1fr auto auto`, align-end, gap `space-md`); results meta row below with `.active-filters` chips (removable `.tag-filter` × = 28px circle inside 36px pill, whole control ≥36 tap) + "Clear All Filters" ghost button.
- **Auto-layout:** each control vertical stack: label (HUG) + field (FILL).
- **Responsive:** <992 secondary controls hide; **<768 entire filter set moves into right slide-over drawer** (`#drawer-filters`) with "Apply (n)" sticky footer 44px + Cancel; open trigger keeps `aria-expanded`; focus returns to trigger on close.
- **Variants:** `Responsive=Inline|Drawer, State=Collapsed|Expanded-with-tags, Count=On` (trigger badge shows active-filter count).
- **Data states:** Skeleton = select/input bars while facet metadata loads; Empty = "No filters applied"; Error+Retry = facets failed → inputs still usable, inline `role="alert"` + Retry; Success = populated options.
- **A11y:** search `type="search"` + `role="searchbox"`; slider shows live value text; URL-synced filters stay deep-linkable (announce "3 filters applied" `aria-live=polite`).
- **Tokens:** surface, border-strong inputs, primary-weak active tags, radius-card/pill.

## 6. StatusBadge sets — `Feedback / Status Badge (Family=…)`
**Purpose:** compact machine-status → human-chip translation. Families & tones (labels come from DB enum mapping, chip text always visible):
- **Appointment:** pending(warn) accepted(primary) rejected(danger) cancelled(neutral) completed(success)
- **Stock:** healthy(success) low(warning) critical(danger) out-of-stock(danger outline)
- **Expiry:** ok(success) upcoming(warning) critical(danger) expired(neutral-strong, strike date)
- **Payment:** paid(success) pending(warning) refunded(info)
- **Account:** pending(warning) approved(success) rejected(danger) suspended(danger outline) archived(neutral)
- **Clinical:** emergency-red-flag (`.badge-emergency` — **emergency scope**, only on red-flag/alert rows)
- **Layout:** inline-flex, pill radius, min-height 24, 12px semibold, leading status **dot** (color + shape not sole cue: dot pairs text).
- **Variants:** `Family, Status, Tone=Solid|Soft|Outline, Size=SM|MD`.
- **Data states:** Skeleton = 60×24 pill bar; Empty = "—" muted text; Error = dashed-border badge "Unknown status" + tooltip (never guess a tone); Success = mapped tone.
- **A11y:** text always; `title`/`aria-label` extension for severity ("Major interaction"); tint text tokens are the `*-text` AA derivatives, not the 4.5-failing base hues.
- **Tokens:** all six weak-bg/weak-border/text triples per theme.

## 7. DoctorCard — `Data / Doctor Card`
**Purpose:** directory result unit: photo w/ initials fallback, name, verified lock, real specialty chip, years exp, rating + count, fee, next-available line, actions.
- **Layout:** vertical stack gap `space-md`: header row (avatar 48 FIXED + name block FILL) → stats list → `doc-next` success tint strip → action row (View Profile outline | Book primary, both FILL, 44px).
- **Auto-layout:** grid child FILL; actions margin-top auto (pin to bottom for equal-height rows).
- **Variants:** `Verified=Yes|No(under review shown as info badge) | Photo=Image|Initials | CTA=Guest(View Profile only — booking gated)|Patient(Book)|Loading | Availability=HasSlots|FullyBooked(secondary CTA "Join waitlist" disabled+reason)` .
- **Responsive:** doctor grid 1/2/3 cols at <640/640–991/≥992; card never compresses below ~300px → at 360 single-column.
- **Data states:** Skeleton = card-shaped skeleton (avatar circle + 3 lines + button bar); Empty = N/A in card itself (handled at grid level); Error+Retry = card-level fallback if fee/rating fetch fails ("Details unavailable · Retry" while name/specialty stay); Success = full card.
- **A11y:** whole card is not one link (nested actions need distinct targets ≥44); name heading `h3` links profile; rating has `aria-label="4.7 out of 5, 32 reviews"`; specialty chip `data-src="db:specialties.name"`.
- **Tokens:** radius-card, space, success-weak next-line, warning stars, money tabular.

## 8. SlotPicker — `Input / Slot Picker (date-strip + chip grid)`
**Purpose:** booking step: horizontal 7/14-day date strip + time-slot grid honoring schedule, leave, bookings.
- **Layout:** vertical gap `space-base`: date strip (row, overflow-x auto, scroll-snap) → weekday legend → slot grid `auto-fill minmax(100px,1fr)` gap `space-sm` → legend of disabled kinds.
- **Auto-layout:** chips FIXED 64px (date) / grid FILL (slots); server supplies slots per selected date (leave-aware).
- **Variants (slot):** `Open | Selected(primary solid) | Booked(dashed, "Booked") | Past(strike, ≥now) | On-leave(warning tint) | Closed(doctor day off)`; date chip adds `Today` emphasis ring + `Off-day` strikethrough.
- **Responsive:** ≥4 cols at 360 (100px min), 7–8 at 768, up to 10 at 1440; strip scrolls at all widths.
- **Data states:** Skeleton = strip + 12 slot bars; Empty = "No open slots this week" + next-opening hint; Error+Retry = "Slot availability failed to load" + Retry (never allow blind booking); Success = chips.
- **A11y:** radios/`role="radiogroup"` + arrow-key semantics documented for dev; disabled slots `aria-disabled` with reason in `aria-label`; conflict path: on submit slot-taken → error state + Reload (see prototypes patient-booking).
- **Tokens:** warning-weak for leave (not emergency red — leave is *not* an emergency), primary selected, border-strong default.

## 9. StarRating — `Input / Star Rating` + `Data / Rating Summary`
- **Display:** 15px stars, filled `--mp-warning`, empty `--mp-border`; numeric `4.7` tabular + `(32)` muted; `aria-label` full sentence. Half-star via clip.
- **Input:** reversed radio row, 44px targets, hover/checked amber; required — inline error "Choose a rating" until set; keyboard: arrow keys.
- **Distribution bars:** rows [5★|bar|%], bar 8px track `surface-muted`, fill warning; counts from reviews table (approved only).
- **Variants:** `Mode=Read|Input|Summary(5→1 bars), Size=SM|LG`.
- **Data states:** Skeleton bars; Empty "No approved reviews yet — be the first"; Error+Retry "Ratings temporarily unavailable"; Success.
- **Tokens:** warning, border, radius-pill, space-sm.

## 10. NotificationBell — `Feedback / Notification Bell + Panel`
- **Layout:** bell button 44px w/ count dot (`--mp-danger`, cap "9+"); panel: header (Mark all read) / `aria-live="polite"` list (cat icon, message, link, relative time) / footer → Notifications page (full paginated table). <768: panel becomes right slide-over drawer.
- **Variants:** `State=Closed|Open, Unread=0|1..9|9+, Responsive=Popover|Drawer`.
- **Data states:** Skeleton 3 item rows; Empty "You're all caught up ✓"; Error+Retry "Couldn't load notifications" + Retry (poll restarts); Success items w/ unread tint `primary-weak-bg`.
- **A11y:** `aria-live="polite"` on list region; mark-read buttons labeled; time `<time datetime>`; counts duplicated as text.
- **Tokens:** surface, danger dot, primary-weak unread, z-dropdown/drawer.

## 11. ChatbotWidget — `AI / FAQ Chatbot`
**Purpose:** floating FAQ assistant, guest-safe, with mandatory guardrails.
- **Layout:** FAB 56px (`--mp-z-chatbot`, label "Ask MediPath"); panel 380×560 desktop / **full-height mobile drawer <640**; anatomy: header (navy) → **PINNED non-dismissible disclaimer bar** (warning tint, sticky top, no close affordance — always in a11y tree) → `aria-live="polite"` stream → suggested-question chips scroller → input row (send primary 44).
- **Message kinds:** bot card, user, **in-stream `EMERGENCY ALERT`** (`.msg-emergency`: emergency red bg per scope, uppercase title, directive text, one-tap `tel:` call buttons 44px), typing skeleton (3 dots).
- **Variants:** `State=Collapsed|Open|MobileFull, Message=Bot|User|Emergency|Typing, SuggestedQs=On|Off, Error=AI-unavailable → deterministic FAQ answer + Retry banner`.
- **Red-flag pipeline:** Stage-1 deterministic match inserts the emergency message and **halts further triage suggestions** until acknowledged; AI (Stage-2) results always captioned "General guidance only — not a diagnosis" [P10].
- **Data states:** Skeleton = typing dots (loading); Empty = greeting + chips ("No conversation yet"); Error+Retry = "AI assistant offline — answering from published FAQs" + Retry (mirrors `AiClient` fallback spec); Success = streamed answers.
- **Tokens:** ink header, warning disclaimer, emergency stream block, primary user, surface/border.

## 12. EmergencyBlocks — `Safety / Emergency Page Kit` *(scoped red zone)*
- **Call card:** row `tel:` link ≥72px: icon disc 44 (`--mp-emergency` bg) + category micro-cap + big tabular number; hover inverts to solid emergency. Grid 1/2/3.
- **Red-flag accordion:** `<details>` per category (cardiac/respiratory/stroke/bleeding/anaphylaxis/pediatric/crisis), 44px summary, warning-triangle bullets colored emergency (content = `emergency_alert_rules` rows, `data-src="db"`).
- **Time-bounded clinic banner:** `.emg-banner` + `starts_at–ends_at` window caption + dismiss only by expiry (admin-managed).
- **Offline fallback note:** dashed card — "Can't connect right now; these numbers stay available · cached <time>" (spec: emergency info works offline).
- **States:** Skeleton cards; Empty = shouldn't happen → shows hotline defaults (never empty emergency list); Error+Retry = custom rules failed → defaults + Retry; Success.
- **A11y:** page region `role="region" aria-label="Emergency"`; `tel:` links are 44px; banner has `role="alert"` when live.

## 13. ConfirmModal — `Overlay / Confirm Modal`
- **Anatomy:** overlay (`--mp-overlay`, z-modal) → dialog 560 (wide 840): head icon+title+×(44) / body (message, **dependency counts row** `12 appointments · 34 prescriptions linked`, tabular pills) / foot right-aligned ghost-cancel + action 44px. Max-height 90vh scroll.
- **Variants:** `Action=Archive|Delete|RoleChange|Cancel|Dispense, Tone=Neutral|Danger|Emergency, ConfirmMode=Click|Typed("type the name to confirm" input gates the danger button until exact match) | ReasonRequired(reject/override textarea mandatory)`.
- **Emergency variant** only for red-flag escalation modals (patient symptom checker) — else danger tone.
- **Data states:** Skeleton = action-area bars (counts loading); Empty = "0 linked records" safe message; Error+Retry = submit failure stays open with inline retry (idempotent token); Success = closes + toast.
- **A11y:** modal pattern: focus trap to first control, Esc→cancel, backdrop click cancel (typed mode: backdrop does NOT dismiss), `aria-modal`, label by title; typed confirm uses `autocomplete=off`.
- **Tokens:** danger/emergency action, overlay, radius-card.

## 14. Stepper6 — `Navigation / Workflow Stepper`
**Purpose:** clinical workflow: Registration → Prescription → Stock Check → Dispense → Billing → Discharged.
- **Layout:** vertical <640 (left rail dots + connector) / horizontal ≥640 (equal columns, connector mid-line). Dot 32–36, name 14 semibold, sub 12 muted.
- **Auto-layout:** items FILL equal; text columns center ≥640, start <640.
- **Variants:** `Steps=3|6, ItemState=Done(success✓)|Current(primary pulse)|Upcoming(neutral)|Blocked(danger — e.g., Stock Check blocked by insufficient stock)|EmergencyHalt`, `Orientation=V|H (responsive)`, `Interactive=Jump-to-stage (tracker) | ReadOnly`.
- **Data states:** Skeleton = 6 dot+line bars; Empty = "No workflow yet" mini-empty; Error+Retry = stage fetch failed → static steps greyed + Retry; Success.
- **A11y:** `aria-current="step"`; blocked stage carries reason text + badge (not color-only); reduced-motion kills pulse.
- **Tokens:** success/primary/danger, border connectors, touch row height.

## 15. Feedback set — Toast · InlineError · Spinner · RetryBanner · UnsavedWarn
- **Toast:** bottom-center mobile / bottom-right desktop, z-toast, `role="status"` (or `role="alert"` for danger), icon disc + message + optional action, auto-dismiss 6s *but* danger toasts persist; stack gap 8. Tones success/danger/info(+ **emergency tone reserved for in-app red-flag notices only**).
- **Inline field error:** `.field-error` under control, `--mp-danger-text` on tint `danger-weak-bg` invalid border, linked `aria-describedby`, icon + specific message ("Slot 10:30 was just booked — choose another").
- **Spinner:** 18px in-button (button disabled + label swaps to "Saving…"), 32 page-level; `aria-hidden` + adjacent `role="status"` text for AT.
- **Retry banner:** full-width `.retry-banner` for failed fetches (spec's named "Retry" affordance): alert icon, cause, Retry primary button (keeps prior filters).
- **Unsaved-changes:** sticky bottom bar above footer when a form is dirty: warning tint, "Discard / Stay".
- **States relationship:** these are the *atoms* composing every component's Error/Loading states.

## 16. PdfButton — `Actions / Download PDF`
- **Layout:** outline button + doc glyph + label (Rx PDF / Invoice PDF); loading = swap to spinner + "Preparing…"; done = success toast.
- **Variants:** `Kind=Prescription|Invoice, Size=MD|SM, State=Idle|Loading|Ready|Disabled(no-permission/ownership check failed)`.
- **Data states:** Skeleton = 120×44 bar in toolbars; Empty/Error: disabled + tooltip "PDF unavailable — record still linked"; Success = file.
- **A11y:** announce generation via `role="status"`; `download` attr; never blocks on client-only ownership (server enforces).
- **Shared builder:** both roles call the same `PdfBuilder` (plan: one template, all roles) — the button is the only surface.

## 17. Cross-component patterns
- **Page skeleton order** (per [P6] top-down): shell (nav/sidebar slots) → data views as `.dataview` blocks → replace each skeleton as its fetch resolves.
- **Empty-state copy rule:** noun + why + way out (CTA or hint), never just "No data".
- **Error contract:** every error UI = message + Retry + last-known timestamp; AI errors name the deterministic fallback in use.
- **Money contract:** component prop `currency` = settings value; format `{{CURRENCY}} 1,250.00`; negatives in parentheses danger tone.
- **Variant-count discipline** [P3]: components expose `State/Tone/Size/Responsive` only — content variants are never baked (specialty/role text is slot data).

---
*Per-screen state inventories and token traces appear in `prototypes/` source comments and DESIGN-RATIONALE.md (delivered after all role batches).*
