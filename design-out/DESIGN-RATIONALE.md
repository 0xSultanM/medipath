# MediPath — Design Rationale (Deliverable D)
**v1.0 · 23 Nov 2026 · cites [P#] = the ten skill principles agreed at kickoff**

The Figma skill was consumed strictly as a methodology/quality bar (its Plugin-API mechanics
were deliberately not exercised — no MCP/API calls; artifacts live only under `./design-out/`).
Each major decision below maps to the principle that justified it.

## 0. Principle key (extracted from the imported Figma skill)
| # | Skill principle (source) | Our implementation |
|---|---|---|
| P1 | Variable collections with modes & explicit scopes (`createVariable`, `scopes`, rules 11/16; variable-patterns.md) | One token set, two modes Light/Dark (`tokens.json` `color.light/dark`; `tokens.scss` mode maps; runtime `[data-theme]` + `:has(#mp-theme)`) |
| P2 | Auto-layout for structurally-related children; HUG/FILL/FIXED sizing (rules 12/12a/12b/12c) | Flex/grid system with `--mp-space-*` gaps, hug=auto width, fill=`flex:1`, fixed=tokens; overlays are the only positioned layer |
| P3 | Variant properties, not content clones (component-patterns, Rule 18) | `State=/Tone=/Size=/Role=/Responsive=` in COMPONENT-SPEC; content always slotted |
| P4 | Inspect conventions before creating (SKILL §9) | Reused docs' Blade kebab names & Bootstrap breakpoint rhythm; no invented conventions |
| P5 | Skeleton-first, top-down construction; shimmer only while loading (rules 5/6 + `placeholder`) | `.sk` sized to final metrics (CLS-safe); shells → data views → content |
| P6 | Validate from evidence; fix root causes; stop when passing (rules 5/6/14) | Live browser probe found the state-switch selector bug + frozen-transition artifact; fixed at root, re-verified |
| P7 | Text = styled tokens; verified weights; no guessed font styles (rule 8; text-style-patterns) | Fixed type ramp incl. H1 32/24/20 + caption 13/18; Inter verified w/ system fallback; tabular numerals tokenized |
| P8 | Purposeful naming & descriptions on library assets (rule 3b) | `Category / Name` + anatomy/usage notes per component |
| P9 | Scope discipline on meaning-bearing color (rule 16 — scopes must be explicit) | Emergency red only inside emergency UI regions (list in §3); danger ≠ emergency everywhere |
| P10 | Deterministic data flow & server-authoritative state over client guessing (docs invariants + skill's return/validate contract) | `{{CURRENCY}}` token, `data-src="db:…"` markers, status-driven action sets |

## 1. Decision → principle table
| Decision | Why | Principle |
|---|---|---|
| Separate `*-text` AA derivatives (#166534/#975307/#C11E1E/#1D4ED8 dark: brighter tints) while keeping mandated hues for fills | Mandated success/warning fail AA as *text* on white/tints (3.3:1, 3.2:1); mandated hues stay visible on bars/dots/fills | P1, P9 |
| Theme = one collection, two modes; toggle `:root:has(#mp-theme:checked)` + `data-theme` persistence in `app.js` | Mirrors Figma variable modes; zero-JS fallback still works in modern browsers | P1 |
| KPI/badge/stepper tones: dot + text + icon, never color alone | Status must survive greyscale/CVD | P9 |
| `State=Success|Skeleton|Empty|Error+Retry` as a first-class variant property, one switch per data view | Skill's variant-property syntax made the 4-state contract inspectable and testable in every screen | P3, P5 |
| Skeleton blocks copy final geometry | “skeleton loaders sized to final layout (no CLS)” + evidence-based validation | P5, P6 |
| SlotPicker disabled kinds (booked/past/on-leave/closed) as variants with `aria-disabled` + reason | Leave-aware booking is the product's core promise; disabled state must explain itself | P3, P10 |
| Override justification gates (type/textarea → enable danger action) | Deterministic-safety-first invariant expressed as UI state machine | P10 |
| ConfirmModal `ConfirmMode=Click|Typed|ReasonRequired` + dependency-count pills | Plan requires typed confirm + soft-archive counts; counts are data, tone is danger | P3, P10 |
| Chatbot: pinned disclaimer bar outside any dismiss control; in-stream `.msg-emergency` halts flow | Regulatory tone; emergency path must be visually distinct from all AI chatter | P7, P9 |
| Stepper6 horizontal ≥640 / vertical <640; blocked stage variant | Skill's sizing enums (FILL columns vs hug rail) map to responsive orientation | P2 |
| Data table → card via `data-label` pseudo-headers <640 | Docs' hard responsive requirement, expressed with zero JS | P2 |
| Role sidebar ink-navy ≥992 → drawer <992; single DOM instance moved, not duplicated | Keep focus order & avoid dual nav landmarks | P2, P8 |
| Emergency escalation modal `role="alertdialog"`, buttons: Call (btn-emergency) / “I'm safe” (quiet) — backdrop does not dismiss | Safety affordance must not be lost to a stray click | P9, P10 |
| Reports: each section an independent fetch; failed tile shows inline Retry; export warns on partial data | KPI-style graceful degradation across the analytics page | P5, P10 |
| Danger-zone ops: password re-entry + typed confirm + dependency counts; red-flag log events use scoped emergency badge (one sanctioned exception because the *content* is a red-flag event) | Destructive ≠ emergency; but a logged red-flag match *is* emergency-domain data | P9 |

## 2. WCAG 2.1 AA self-audit (computed ratios, Nov 2026)
Pairs are (foreground / background). AA normal text needs 4.5:1, large ≥3:1, UI boundaries ≥3:1.

### Light theme
| Pair | Ratio | Pass | Where |
|---|---|---|---|
| #0E7C86 text / #FFFFFF | 4.95 | ✅ | links, outline btn text |
| #FFFFFF / #0E7C86 (primary button) | 4.95 | ✅ | all primary buttons |
| #FFFFFF / #0B6B73 (hover) | 7.18 | ✅ | hover state |
| #0B2545 / #F6F9FB and / #FFF | 14.55 / 15.39 | ✅ | headings |
| #1F2A37 / #FFF | 14.54 | ✅ | body |
| #556576 muted / #FFF · / #F6F9FB | 5.98 · 5.66 | ✅ | captions, hints |
| #166534 success-text / #FFF · / #E6F6EC | 7.13 · 6.37 | ✅ | success badges/alerts |
| #975307 warning-text / #FFF · / #FBEDDA | 5.91 · 5.13 | ✅ | warning family |
| #C11E1E danger-text / #FFF · / #FDEAEA | 6.05 · 5.22 | ✅ | danger family |
| #1D4ED8 info-text / #E5EDFB · #2563EB / #FFF | 5.69 · 5.17 | ✅ | info family |
| #B91C1C emergency text / #FFF · #FFF / #B91C1C · #FFF / #7F1D1D | 6.47 · 6.47 · 10.02 | ✅ | emergency banner/call cards |
| #748BA6 input border / #FFF · / #F6F9FB | 3.15 · ~3.0 | ✅ UI 3:1 | inputs, chips |
| #0E7C86 focus ring / #FFF | 4.95 | ✅ ≥3 | global focus-visible |
| white sidebar text / #0B2545 | 15.39 | ✅ | role nav |
| note: base #16A34A/#D97706 kept ONLY on fills/bars/dots (non-text) | — | ✅ by construction | |

### Dark theme
| Pair | Ratio | Pass |
|---|---|---|
| #E8EFF7 / #0B1220 · / #131E30 | 16.16 · 14.43 | ✅ |
| #A7B6CC muted / #131E30 · / #0B1220 | 8.13 · 9.10 | ✅ |
| #5BC9D4 primary-text / #0B1220 · / #131E30 · / #10343B | 9.58 · 8.56 · 6.82 | ✅ |
| #FFFFFF / #0E7C86 dark button | 4.95 | ✅ |
| #4ADE80 / #131E30 · / #123522 | 9.59 · 7.73 | ✅ |
| #FBBF24 / #131E30 · / #3A2A10 | 10.02 · 8.28 | ✅ |
| #F87171 / #131E30 · / #3B1A1C | 6.04 · 5.62 | ✅ |
| #82ADFF / #131E30 · #93C5FD / #132446 | 7.46 · 8.51 | ✅ |
| #FCA5A5 / #131E30 (emergency text) · #FFF / #B91C1C banner | 8.81 · 6.47 | ✅ |
| #FFFFFF / #081627 dark sidebar | 17.41 | ✅ |

### Non-color & interaction checks
| Criterion | Status | Evidence |
|---|---|---|
| 1.4.1 Use of color (status) | ✅ | every badge = dot + text; deltas carry ▲/▼; slot kinds add strike/label/caption |
| 1.4.3/4 Contrast (text AA) | ✅ | tables above; both themes |
| 1.4.11 Non-text contrast ≥3:1 | ✅ | borders #748BA6 (3.15–3.3), focus rings 4.95+/8.56, chart bars vs bg ≥3 |
| 2.1.1 Keyboard | ✅ | checkboxes/radios/details drive drawers·tabs·states·modals; buttons ≥44px; skip-link on every page |
| 2.4.1 Bypass blocks | ✅ | `.skip-link` → #main everywhere |
| 2.5.5 Target size (AAA adopted where required by brief) | ✅ | `--mp-touch:44px` on btn/bell/date/slot/tab-label |
| 3.3.1/2 Error IDs; 3.3.4 | ✅ | `aria-describedby`, inline `.field-error` + tint border + icon |
| 4.1.3 Status messages | ✅ | `role="status"`/`alert`, `aria-live` on chat stream & notif drawer & slot fetch |
| 1.3.2 Meaningful order | ✅ | DOM = visual order; panes keep source order |
| Reduced motion | ✅ | skeleton shimmer, stepper pulse, transitions off under `prefers-reduced-motion` |
| Landmarks | ✅ | nav/main/aside per role + `aria-label` per landmark |

## 3. Emergency-red scope ledger (grep-audited)
Occurrences of `emergency|cta-emergency|emg-banner|call-flag…` classes are confined to:
landing strip & chatbot intercept · navbar/drawer emergency CTA (all roles' guest nav) ·
emergency page · symptom-checker escalation · safety-console red-flag rule badges ·
activity-log red-flag event badge. Everything else destructive/error = `danger` family.
Rationale documented in-file where a reader might question it (04-emergency footer,
07-allergies modal comment, 09-activity caption, 11-safety alert).

## 4. Deliverable traceability
- Every token in prototypes resolves to `tokens.css` vars (compiled from `tokens.scss`/`.json`).
- Every component instance comment in prototypes cites its `COMPONENT-SPEC.md §n`.
- Data-state switchers per spec §0/§17: 44 data views across 46 role screens, each with all four states.
- Role/specialty labels carry `data-src="db:…"` — none hardcoded in components.
- Money renders `{{CURRENCY}}` token in 81 places; zero literal `$` in any prototype HTML (audit script run each batch).

## 5. Open questions for product/dev
1. **Walk-in → claim-code collision UX:** spec says patient auto-approve toggle exists; if OFF, do walk-in patients get a *pending* holding screen (we assume guest directory + receipt only)? Confirm.
2. **Reschedule race:** after “slot just taken”, should the flow preserve the *reason* step draft, or restart? (we assume preserved).
3. **Refund reversibility:** does refund auto-reverse dispensed stock (ledger −) or require separate adjustment? We modeled separate explicit action — confirm with pharmacy SOP.
4. **Revoked doctor mid-prescription:** should in-flight pending_dispense Rx be auto-blocked at the gate (we show hard block) or grandfathered? Legal review likely needed.
5. **Currency placement:** symbol before/after + decimal separators per locale — CurrencyFormatter spec needed (design shows `{{CURRENCY}} 1,200.00`).
6. **2FA for admin/pharmacists:** checkbox shown disabled-as-planned; is TOTP in scope for this semester?
7. **Offline emergency cache:** we assume service-worker pre-cache of contacts/rules; confirm hosting allows SW.
8. **Patient auto-approve toggle** default value on fresh install (we show ON).
9. **Commission visibility:** leaderboard revenue share to admins only assumed; should doctors see their own contribution?
10. **Dark-mode default:** user-preference vs system default priority — we follow System→user override chain.
