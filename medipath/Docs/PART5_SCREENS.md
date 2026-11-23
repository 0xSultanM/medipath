# Part 5 — Patient AI Features (complete)

Visual acceptance: `design-out/prototypes/patient/04-symptom-checker.html` plus the safety
patterns in `design-out/COMPONENT-SPEC.md` and `DESIGN-RATIONALE.md`.

## Route map

| Capability | Route | Implementation |
|---|---|---|
| Two-stage symptom check | `GET /patient/symptom-checker` | `Patient\SymptomCheckerController@__invoke` |
| Stage 1 + Stage 2 result | `POST /patient/symptom-check` | `SymptomTriageService::check` |
| Specialist recommendation | inside the check response | `SymptomSpecialtyMap` + active `Specialty` cross-validation |
| Emergency escalation | inside the check response + emergency modal | `EmergencyRuleEngine` + active `EmergencyContact` |

The endpoint is patient-only, CSRF-protected, throttled (`chatbot` limiter) and returns
`Cache-Control: no-store`.

## Two-stage pipeline

1. **Deterministic red-flag gate**
   - `EmergencyRuleEngine` normalizes the free-text symptoms and matches active rules by keyword.
   - A match immediately halts triage, stores a `symptom_check_logs` row with `emergency_triggered=true`
     and writes `safety.emergency_rule_matched` to the append-only activity log.
   - The UI opens an `alertdialog` emergency modal with the active hotlines, the rule's action
     directive and a “while you wait” list. No AI call is made and the form stays halted until the
     patient starts a new check.
2. **Specialist + guidance**
   - Symptom keywords are scored against `symptom_specialty_map`; the top active mapping wins.
   - The default fallback is **General Medicine** when no mapping matches.
   - When AI is enabled **and** an OpenAI key/model is configured, Stage 2 asks for strict JSON
     (`summary`, `urgency`, `questions`, `specialty_hint`). A returned specialty hint is
     cross-validated against the active specialty table before use.
   - Any provider failure/timeout/invalid JSON falls back to deterministic guidance and is clearly
     labelled `deterministic_fallback` in the UI — fallback is never disguised as AI.
   - The response includes questions for the doctor, urgency (`self_care|monitor|urgent`), the
     recommended specialty, verified doctors when available, and booking links.

## Safety & privacy

- Red flags always win; the AI path is never reached on an emergency match.
- The AI is explicitly told: no diagnosis, no dosing, no certainty; output is schema-validated.
- Only symptom text/duration/severity/onset/body-region are sent to the provider. The patient's
  name, email, patient code and record history are not sent.
- Emergency contacts come from the admin-managed DB list, never hardcoded.
- Every check is owner-only, logged with patient id/source/result and visible in Recent checks.
- The chat widget remains available for FAQ questions, but the triage page labels which source
  produced the answer.

## Files

- `app/Services/SymptomTriageService.php`
- `app/Http/Controllers/Patient/SymptomCheckerController.php`
- `resources/js/pages/Patient/SymptomChecker.tsx`
- `tests/Feature/PatientPart5Test.php`
- `tests/Browser/PatientTriageTest.php`

## Verification

```bash
php artisan test --compact                         # 146 passed, 1,506 assertions
php artisan dusk --without-tty                     # 24 passed, 319 assertions
npm run typecheck && npm run build                 # clean
```

Feature tests cover: emergency halt + audit, deterministic fallback with mapped specialty, AI
success + specialty cross-validation, provider failure fallback, consent validation and role
blocking. Dusk covers the guidance and emergency-modal paths. The triage page is included in the
five-width patient breakpoint sweep.

## Demo path

1. Sign in as `patient@medipath.test` / `MediPath#2026` → **AI Symptom Checker**.
2. Enter “sore throat and fever for three days”, consent, run check → guidance, urgency, questions,
   ENT recommendation and the labelled fallback warning (AI key is intentionally absent in demo).
3. Start a new check → “crushing chest pain spreading to my left arm” → emergency modal opens with
   one-tap hotlines; close it and note the page stays halted.
4. Inspect the recent-check entry and `/admin/activity?q=emergency` as admin.

## Deviations

1. **No production AI key is committed.** The schema-validated OpenAI path is implemented and
   tested with `Http::fake`; the default demo intentionally uses deterministic fallback.
2. **Specialty recommendation is rule-first.** The AI hint is a suggestion only and must match an
   active DB specialty, preventing hallucinated specialization.
3. **Recent checks show a compact summary**, not the full raw payload; full source/urgency data is
   stored in `symptom_check_logs` for clinical audit.