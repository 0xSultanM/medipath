# Part 8 — Pharmacist Portal (complete)

Visual acceptance: `design-out/prototypes/pharmacist/01-dashboard.html` … `08-workflow-tracker.html`.
Every route is behind `auth + role:pharmacist + pharmacist.approved`; the pharmacist is resolved
from `auth()->user()->pharmacist`, never from a posted id.

## Route map

| Screen / action | Route | Controller |
|---|---|---|
| Dashboard | `/pharmacist/dashboard` | `Pharmacist\WorkspaceController@dashboard` |
| Pending queue | `/pharmacist/prescriptions` | `Pharmacist\PrescriptionController@index` |
| Safety gate | `/pharmacist/prescriptions/{prescription}` | `PrescriptionController@show` |
| Refuse prescription | `POST /pharmacist/prescriptions/{prescription}/refuse` | `PrescriptionController@refuse` |
| Dispense & bill | `/pharmacist/prescriptions/{prescription}/dispense` | `PrescriptionController@dispensePage` |
| Live totals quote | `POST /pharmacist/prescriptions/{prescription}/quote` | `PrescriptionController@quoteTotals` |
| Commit dispense | `POST /pharmacist/prescriptions/{prescription}/dispense` | `PrescriptionController@dispense` |
| Inventory | `/pharmacist/inventory` | `Pharmacist\InventoryController@index` |
| Add / edit medicine | `POST /pharmacist/inventory`, `PUT …/{medicine}` | `InventoryController` |
| Restock | `POST /pharmacist/inventory/{medicine}/restock` | `InventoryController@restock` |
| Archive | `POST /pharmacist/inventory/{medicine}/archive` | `InventoryController@archive` |
| Alerts console | `/pharmacist/alerts` | `WorkspaceController@alerts` |
| Invoices | `/pharmacist/invoices` | `Pharmacist\InvoiceController@index` |
| Invoice payment state | `POST /pharmacist/invoices/{invoice}/status` | `InvoiceController@updateStatus` |
| Invoice PDF | `/pharmacist/invoices/{invoice}/pdf` | `InvoiceController@pdf` |
| Workflow tracker | `/pharmacist/workflow?q=` / `?patient=` | `WorkspaceController@workflow` |
| Notifications | `/pharmacist/notifications?filter=all\|unread` | `Pharmacist\NotificationController` |
| Settings | `/pharmacist/settings` + profile/password/preferences | `Pharmacist\ProfileController` |

## What ships

* **Dashboard** — pending prescriptions with age and stock state, low/critical/out-of-stock counts,
  today's billed total and dispense count, plus the urgent stock/expiry queue.
* **Pending queue** — server-filtered by search, stock state and oldest/newest; waiting age, item
  names, sealed-override flag and a direct gate link.
* **Safety gate** — re-runs the deterministic allergy/class, pairwise interaction, stock and expiry
  checks at the counter. Shows patient allergies, doctor notes, AI summary with its status, every
  blocker and the sealed override. Dispense stays locked until the pharmacist acknowledges identity,
  dose, quantity and counselling (and the override, when present). Refusal requires a reason and
  cancels the prescription with doctor + patient notifications.
* **Dispense & bill** — full prescribed quantities only; server-calculated live totals (subtotal,
  discount, net) via a no-store quote endpoint so the client never formats money. Consultation fee
  prefills from the doctor profile, discount can be none/fixed/percent, payment method comes from
  config, and payment can be marked received immediately.
* **Atomic dispense** — one transaction locks the prescription and every medicine row, validates
  stock, deducts stock, writes an append-only ledger entry per medicine, issues the invoice with a
  currency snapshot, closes the prescription as dispensed, writes activity logs and notifies the
  patient and doctor. Any failure rolls all of it back; the returned page is the receipt.
* **Inventory** — search/category/threshold/expiry filters, add/edit metadata, opening stock as a
  ledger entry, restock with batch/expiry/price/reference, soft archive, and the latest ledger rows.
* **Alerts** — stock, expired, critical ≤30 days and upcoming 31–90 day tiers with counts, values and
  restock links. Expired batches are excluded from doctors' live search.
* **Invoices** — search by invoice/patient/Rx, status and method filters, pending/paid/refunded
  counts, today's billing total, payment-state updates (refund requires a reason) and PDF download.
* **Workflow tracker** — six derived stages (registration → prescription → stock → dispense → billing
  → discharge) with truthful substatus, blocked safety state, jump links and invoice summary.
* **Notifications & settings** — read-state feed through the shared ownership-checked endpoints;
  verified credential summary, profile/pharmacy name, password rotation with session revocation, and
  theme/locale/timezone/date preferences.

## Safety & integrity

* Pharmacy routes require an approved pharmacist profile; all mutations resolve the pharmacist from
  the authenticated user.
* Stock is only changed through `InventoryService` / `PharmacyDispenseService`, always in a
  transaction and always with a ledger row. Existing rows are immutable.
* Dispense re-evaluates safety inside the transaction and requires a sealed doctor override for
  allergy/contraindicated conflicts. Expired batches and insufficient stock are hard failures.
* Partial quantities are rejected: dispense the full prescribed quantity or refuse the prescription.
  This prevents a prescription being closed while only part of the course was supplied.
* Invoice totals, discount and currency snapshot are computed server-side; the client only renders
  pre-formatted strings (`CurrencyFormatter`).
* Refunds never reverse stock silently; stock movement remains an explicit ledger action.
* Workflow status is derived from the latest prescription/invoice and current stock, so it cannot
  drift from the underlying records.

## Verification

```bash
php artisan test --compact                         # 174 passed, 2,041 assertions
php artisan dusk --without-tty                     # 36 passed, 470 assertions
npm run typecheck && npm run build                 # clean
```

Lighthouse (in-app Chromium), both themes — Accessibility **100**, Best Practices **100**, SEO
**100** on all ten pharmacy screens, including the safety gate and both dispense states
(draft totals and receipt).

Dusk covers all ten screens at **360 / 640 / 768 / 992 / 1440** and captures the add-medicine,
restock, invoice-status and gate/drafts. Screenshots are `tests/Browser/screenshots/part8-*`.

## Demo path

1. Sign in as `pharmacist@medipath.test` / `MediPath#2026`.
2. Dashboard → open the oldest pending prescription → review the gate checks.
3. Proceed to **Dispense & bill** → adjust discount/payment → confirm the final gate → see the receipt
   and download the invoice PDF.
4. Alternative: **Refuse dispense…** on a blocked prescription and confirm the doctor notification.
5. Inventory → add a medicine (opening stock ledger), restock a low item, inspect the ledger, archive.
6. Alerts → switch between stock/expired/critical/upcoming tiers.
7. Invoices → mark one paid, refund another with a reason, download the PDF.
8. Workflow → search `PT-1001` to see all six stages; Settings → change pharmacy name/preferences.

## Deviations

1. **Workflow status is derived, not stored in `patients.workflow_status`.** The prototype names a
   column, but a single mutable patient column cannot represent per-visit progress; derivation from
   the latest prescription/invoice keeps the tracker truthful without schema drift.
2. **Partial dispensing is rejected.** The prototype shows editable dispense quantities; the
   implementation requires the full prescribed quantity or an explicit refusal, because the schema
   has no partial-dispense state.
3. **Expired-batch quarantine is replaced by batch replacement.** Restocking a new batch writes the
   ledger entry; there is no destructive delete of expired stock.
4. **Invoice line prices use the prescription snapshot**, not the current catalogue price, protecting
   historical bills from later price changes.
5. **The AI safety text remains advisory** and is re-displayed with its provenance; deterministic
   checks decide every blocker.
6. **Notification read state uses the shared API** so bell and page always agree.