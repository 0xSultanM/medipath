# Part 7 — Doctor Prescribing & AI (complete)

Visual acceptance: `design-out/prototypes/doctor/07-prescription-builder.html` plus the doctor
shell for the two new surfaces. All routes remain behind `auth + role:doctor + verified.doctor`.

## Route map

| Screen / action | Route | Controller |
|---|---|---|
| Prescription history + search | `/doctor/prescriptions` | `Doctor\PrescriptionController@index` |
| Prescription builder | `/doctor/prescriptions/new?patient=` | `Doctor\PrescriptionController@create` |
| Live deterministic safety check | `POST /doctor/prescriptions/safety-check` | `PrescriptionController@safetyCheck` |
| AI safety review (advisory) | `POST /doctor/prescriptions/ai-review` | `PrescriptionController@aiReview` |
| Save prescription | `POST /doctor/prescriptions` | `PrescriptionController@store` |
| Prescription PDF | `/doctor/prescriptions/{prescription}/pdf` | `PrescriptionController@pdf` |
| Doctor notifications | `/doctor/notifications?filter=all\|unread` | `Doctor\NotificationController@__invoke` |
| Mark read (shared) | `POST /notifications/{notification}/read` | `NotificationController@markRead` |
| Mark all read (shared) | `POST /notifications/read-all` | `NotificationController@markAllRead` |

## What ships

* **Prescription history** — server-scoped to the signed-in doctor, searchable by prescription code,
  patient name or patient code, filterable by every `PrescriptionStatus` value, paginated, with a
  sealed PDF download and a jump back to the patient chart. Override prescriptions are flagged in the
  row.
* **AI safety summary** — an explicit, opt-in review in the builder. It runs the deterministic
  allergy/interaction/stock/expiry evaluation first, then requests a provider summary when
  `ai_enabled`, `ai_model` and `OPENAI_API_KEY` are configured. Without a provider (or on timeout,
  HTTP failure or malformed JSON) the panel shows a **labelled deterministic fallback** — never a
  silent failure and never an unlabelled AI claim.
* **Provenance on save** — `ai_status` and `ai_safety_summary` are written with the prescription
  inside the same transaction as items, notes, safety snapshot and override seal. Requesting AI on
  the client sets `ai_requested`; the server repeats the deterministic checks before trusting either
  path.
* **Doctor notifications** — authenticated feed with all/unread tabs, counts, pagination, action
  links and per-item read state. Read state is owned by the shared notification endpoints, so the
  bell/panel and the page always agree; cross-user attempts return `403`.
* **Role-aware bell** — the notification bell's "View all" target now follows the active role
  (`/doctor/notifications` for doctors, `/patient/notifications` otherwise).
* **Navigation** — doctor sidebar gains **Prescription History** and **Notifications** (unread
  badge), both with exact-match highlighting so the builder and history never highlight together.

## Safety & integrity

* The AI is **advisory only**. Deterministic checks decide blocking, override requirements, stock and
  expiry; the AI text cannot remove a blocker or change a snapshot.
* Provider output is accepted only as strict JSON with a non-empty `summary`, capped in length, and
  always labelled `AI-assisted · advisory`. Every other outcome is `deterministic_fallback`.
* Saving without an AI review stores a deterministic summary with `ai_status=not_requested`; saving
  after a review stores the returned status, so the record always says where the text came from.
* History is read-only and scoped by `doctor_id`; another doctor's rows never appear in lists or
  searches, and PDFs re-check ownership.
* Notification read endpoints validate ownership before updating.
* The builder now labels every inline item input for screen readers (WCAG AA), covering the draft
  table as well as the search, notes and override fields.

## Verification

```bash
php artisan test --compact                         # 161 passed, 1,765 assertions
php artisan dusk --without-tty                     # 31 passed, 397 assertions
npm run typecheck && npm run build                 # clean
```

Lighthouse (in-app Chromium), both themes:

* `/doctor/prescriptions` — Accessibility **100**, Best Practices **100**, SEO **100**
* `/doctor/notifications` — Accessibility **100**, Best Practices **100**, SEO **100**
* `/doctor/prescriptions/new` **with an item and the AI panel open** — Accessibility **100**,
  Best Practices **100**, SEO **100**

Dusk sweeps 360 / 640 / 768 / 992 / 1440 for both new screens; screenshots are
`tests/Browser/screenshots/part7-*`.

## Demo path

1. Sign in as `ayesha.rahman@medipath.test` / `MediPath#2026`.
2. **Prescription History** → search a patient, filter to *Dispensed*, open a PDF.
3. **Prescribe** → choose a treated patient → search `Amlodipine` → add it.
4. In **Safety checks**, press **Generate summary** → the panel appears as
   *Deterministic fallback* with its source line (or *AI-assisted · advisory* when a provider is
   configured). Editing any item clears the summary so it can never describe stale items.
5. **Notifications** → unread tab → mark one read, then mark all read; the bell count updates from
   the server response.

## Deviations

1. **Provider-backed AI is optional.** The demo intentionally runs the deterministic fallback unless
   `OPENAI_API_KEY` plus `ai_enabled`/`ai_model` are configured; no key is committed.
2. **The AI panel is on demand, not automatic.** Prescribers choose when to request it, so a slow or
   unavailable provider never delays the deterministic safety gate.
3. **Prescription history is read-only.** Corrections remain a new prescription plus pharmacy
   cancellation workflow, preserving the append-only clinical trail.
4. **Notifications reuse the shared read API** instead of doctor-specific endpoints; ownership is
   enforced centrally.
5. **Stock is still validated, not deducted, at save.** Deduction remains the pharmacy dispense
   transaction in Part 8.