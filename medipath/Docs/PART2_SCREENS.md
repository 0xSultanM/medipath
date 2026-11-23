# Part 2 — Admin Core (complete)

Visual acceptance: `design-out/prototypes/admin/01-dashboard.html` … `05-patients.html`.
All screens use the existing Admin role shell (`RoleLayout`), server-side data, and the shared
four-state `DataView` contract.

## Route map

| Screen | Prototype | Route(s) | Controller |
|---|---|---|---|
| Master dashboard + system overview | `admin/01-dashboard.html` | `GET /admin/dashboard?range=7\|30\|90\|365` | `Admin\DashboardController` |
| User management | `admin/02-users.html` | `GET /admin/users` (search/role/status/idle/pager) | `Admin\UserController@index` |
| Create internal user | `admin/02-users.html` | `POST /admin/users` | `Admin\UserController@store` |
| Edit contact | `admin/02-users.html` | `PUT /admin/users/{user}` | `Admin\UserController@update` |
| Atomic role change | `admin/02-users.html` | `POST /admin/users/{user}/role` | `Admin\UserController@changeRole` |
| Suspend / restore / archive | `admin/02-users.html` | `POST /admin/users/{user}/suspend\|restore\|archive` | `Admin\UserController` |
| Approval queue + credential preview | `admin/03-approvals.html` | `GET /admin/approvals?tab=verification`, `POST …/{approval}/approve\|reject`, `GET …/{approval}/credential` | `Admin\ApprovalController` |
| Doctor verification decisions | `admin/03-approvals.html` | `POST /admin/doctors/{doctor}/verification` (`approve\|resubmit\|revoke`), `GET …/credential` | `Admin\ApprovalController@verify` |
| Doctor CRUD | `admin/04-doctors.html` | `GET/POST /admin/doctors`, `PUT /admin/doctors/{doctor}`, `POST …/archive\|restore` | `Admin\DoctorController` |
| Patient CRUD | `admin/05-patients.html` | `GET/POST /admin/patients`, `PUT /admin/patients/{patient}`, `POST …/archive\|restore` | `Admin\PatientController` |
| Portal-as-patient preview | `admin/05-patients.html` | `GET /admin/patients?tab=portal&portal={id}` and `GET /admin/patients/{patient}/portal` | `Admin\PatientController@index/portal` |

## Security & integrity

* **Idle timeout** — `AdminIdleTimeout` (alias `admin.idle`) tracks `admin.last_activity` server-side;
  inactivity beyond `config('medipath.security.admin_idle_minutes')` (15) invalidates the session
  and returns the admin to `/login`. JSON clients receive `401` with `code=admin_idle_timeout`.
* **Session revocation** — `users.session_version` is a monotonic auth generation. Login/registration
  stamp it into the session; `EnsureSessionFresh` rejects stale generations. Suspend, archive, role
  change, password reset and doctor resubmission/revocation increment it and delete the user's
  `sessions` rows.
* **Role changes are atomic** — old role profile is archived, new doctor/pharmacist profile and
  specialty/fee/licence are created or revived, user role flips, sessions clear, notification +
  immutable activity entry commit in one transaction. A doctor role stays `verification_status=pending`
  until licence verification.
* **Governance guards** — admins cannot change their own role, suspend or archive themselves, and the
  final active admin cannot be suspended/demoted/archived. Every typed archive validates the exact
  account name server-side.
* **Credentials are private** — the `credentials` disk is private and never web-served. The preview
  route is admin-only, emits `Cache-Control: no-store, private`, `nosniff`, `SAMEORIGIN`, and writes
  `credential.previewed` on every open. Seeded approvals without a stored binary render a clearly
  marked, escaped demo preview instead of a real document.
* **Soft delete only** — doctors and patients use `archived_at` (not destructive deletes). Dependency
  counts (appointments/prescriptions/reviews/schedules for doctors; appointments/prescriptions/invoices
  for patients) are shown in the typed-confirm dialog and recorded in the audit detail.
* **Portal preview is read-only & audited** — every as-patient open writes `patient.portal_viewed`.

## UI notes

* Dashboard KPIs: users, awaiting action, appointments, revenue, commission (from effective-dated
  revenue config), prescription completion. System overview = DB, queue, storage, AI fallback.
  Attention queue links to approvals/reviews/records. 12-week booked vs completed trend.
* Every data view exposes Success / Skeleton / Empty / Error+Retry (`?state=` also works).
* Money is always `CurrencyFormatter` output (`Money` primitive); no literal `$`. CRM labels for
  roles/status/verification/specialty come from the server.
* Dense admin tables opt into a documented medium-width card mode between **640–1199px**
  (`tbl-cards-md`) so five-plus columns stay readable; below 640 the global COMPONENT-SPEC §4 card
  conversion applies. Tables at ≥1200 keep the desktop layout.
* Emergency red remains scoped to emergency surfaces; routine destructive actions use danger tone.
* Portal headings are `h1 → h2 → h3` (Lighthouse heading-order clean).

## Verification

```bash
php artisan test --compact                         # 119 passed, 1,118 assertions
php artisan dusk --without-tty                     # 15 passed, 163 assertions
npm run typecheck && npm run build                 # clean
```

Lighthouse (in-app Chromium) on `/admin/dashboard`, `/admin/users`, `/admin/approvals`,
`/admin/doctors`, `/admin/patients`, and the as-patient preview: **Accessibility 100**,
Best Practices 100, SEO 100 in **both dark and light** themes.

Dusk covers all five widths **360 / 640 / 768 / 992 / 1440** for each admin screen (no horizontal
document overflow, sidebar threshold), table→card conversion, credential dialog, typed archive
dialogs, and the portal tab. Screenshots live in `tests/Browser/screenshots/part2-*`.

## Demo path

1. Sign in as `admin@medipath.test` / `MediPath#2026`.
2. Dashboard → range chips → state switch (Skeleton/Empty/Error) → attention links.
3. Users: filter by Pending, open a role dialog; try the final-admin/self guards.
4. Approvals: preview a private credential, reject with a 10+ character reason, switch to Doctor
   verification, approve/request resubmission/revoke.
5. Doctors: add/edit, archive with dependency counts + exact typed name, restore.
6. Patients: register/edit, archive, then **Portal view** for read-only prescription/billing history.