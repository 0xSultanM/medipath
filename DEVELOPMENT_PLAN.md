# MediPath: AI-Powered Doctor Appointment & Medicine Safety System
# Complete Technology Stack & Development Plan

---

## 1. UML & SYSTEM ARCHITECTURE SUMMARY

Based on the project documentation, the system uses a **5-role architecture** with strict role-based access control (RBAC):

### Actors & Roles
| # | Role | Access Level | Auth State |
|---|------|-------------|------------|
| 0 | **Guest** | Public pages only (Doctor Directory, Emergency Info, FAQ Chatbot, Registration, Login) | Unauthenticated |
| 1 | **Patient** | Own appointments, prescriptions, bills, AI symptom checker, reviews, profile | Fully authenticated |
| 2 | **Doctor** | Schedule, appointments, patient charts, prescriptions, AI safety checks, reviews | Verified + Approved |
| 3 | **Pharmacist** | Inventory, dispensing, billing, stock alerts, workflow tracker | Approved |
| 4 | **Admin** | Full system governance (users, doctors, patients, appointments, revenue, analytics, settings, data management) | Admin |

### Key Relationships
- **User → Patient**: One-to-one (permanent user_id → patient_id link)
- **User → Doctor**: One-to-one (user_id → doctor_id, with verified credentials)
- **User → Pharmacist**: One-to-one (user_id → pharmacist_id, with license)
- **Doctor → Prescription**: One-to-many
- **Patient → Prescription**: One-to-many
- **Patient → Appointment**: One-to-many
- **Patient → Invoice**: One-to-many
- **Prescription → Medicine Items**: One-to-many
- **Appointment → Doctor + Patient**: Many-to-one each

### Core Workflows (from docs)
1. **Guest Registration → Admin Approval → Role Activation**
2. **Patient Booking → Doctor Accept/Reject → Consultation → Prescription → Dispensing → Billing → Discharge**
3. **AI Symptom Checker → Specialist Recommendation → Booking**
4. **Doctor Prescribing → Allergy Check → Drug Interaction Check → AI Safety → Pharmacist Dispense**
5. **Pharmacist Dispense → Stock Deduction → Billing → Patient Discharge (Atomic ACID Transaction)**

---

## 2. TECHNOLOGY STACK RECOMMENDATION

### Primary Stack: Laravel + React + Bootstrap 5

| Layer | Technology | Justification |
|-------|-----------|---------------|
| **Backend Framework** | **Laravel 11** | Primary requirement. Excellent for RBAC, Eloquent ORM, queues, notifications, API resources |
| **Frontend Core** | **React 18 + TypeScript** | Complex interactive UIs (AI symptom checker, real-time dashboards, workflow tracker). Component reusability aligns with doc's "reuse without duplicating code" mandate |
| **CSS Framework** | **Bootstrap 5.3** | Responsive grid, mobile-first, WCAG 2.1 AA accessible components. Built-in dark mode support |
| **Build Tool** | **Vite** | Ships with Laravel 11. Fast HMR, optimized builds |
| **Database** | **MySQL 8.0 / MariaDB** | ACID transactions, row-level locking, JSON columns for settings/preferences |
| **Cache & Sessions** | **Redis** | Session storage, queue driver, rate limiting, real-time pub/sub |
| **Real-Time** | **Laravel Reverb + Echo** | In-app notifications (Patient, Doctor, Pharmacist bell badges). WebSocket-based |
| **Queue/Jobs** | **Laravel Queues (Redis)** | Email sending, PDF generation, AI API calls, notification dispatch |
| **AI Integration** | **OpenAI API / Claude API** | Symptom checker, medicine safety evaluation, FAQ chatbot. Server-side only |
| **PDF Generation** | **Laravel DomPDF / Barryvdh** | Prescription PDFs, Invoice PDFs, Analytics exports |
| **CSV Export** | **Laravel Excel (Maatwebsite)** | Formula-safe CSV exports, bulk imports with dry-run |
| **Search** | **Laravel Scout + MySQL Full-Text** | FAQ chatbot search, doctor search, patient search, invoice search |
| **File Storage** | **Laravel Filesystem (local/S3)** | Credential documents (private), profile photos |
| **Auth** | **Laravel Sanctum + Session** | HTTP-only cookies, session-based auth, role gates, brute-force protection |
| **Testing** | **Pest / PHPUnit + Laravel Dusk** | Automated role-gate tests, ACID transaction tests, browser tests |

### Alternative (Simpler): Laravel + Blade + Livewire + Bootstrap 5

If the team prefers less JavaScript complexity, Laravel Livewire can handle reactive components (dashboards, workflow tracker) without React's build chain. Choose this if the team is more backend-heavy.

### Why NOT Angular?
- Angular's learning curve is steeper for a semester project
- React/Livewire integrate more naturally with Laravel's ecosystem
- The doc's emphasis on reusability maps better to React components

### Why Bootstrap (not Tailwind)?
- Bootstrap 5 provides ready-made responsive components (navbars, drawers, cards, modals, skeleton loaders)
- Built-in accessibility (WCAG 2.1 AA)
- Faster initial development
- However, Tailwind CSS could be substituted if the team prefers utility-first

---

## 3. RESPONSIVE DESIGN STRATEGY

The documentation explicitly mandates responsive behavior at specific breakpoints:

| Device | Max Width | Layout Behavior |
|--------|-----------|----------------|
| Small Mobile | 360px+ | Single column, stacked cards, slide-over drawer navigation |
| Mobile | <640px | Card layouts replace tables, touch targets ≥44×44px |
| Tablet | <768px | Collapsed hamburger menu, 2-column grids, mobile-friendly tables |
| Desktop | ≥768px | Full tables, sidebar navigation, 3-column doctor grids, multi-panel dashboards |

### Key Responsive Patterns Required:
- **Navigation**: Sidebar → slide-over drawer on mobile
- **Tables**: Convert to stacked cards below 640px
- **Doctor Grid**: 1 col (mobile) → 2 cols (tablet) → 3 cols (desktop)
- **Dashboards**: KPI cards stack vertically on mobile
- **Filters**: Collapse secondary filters into bottom drawer on mobile
- **Forms**: Full-width inputs on mobile
- **Modals**: Full-height bottom sheets on mobile
- **Touch Targets**: Minimum 44×44px for all interactive elements
- **Skip-to-content**: WCAG 2.1 AA compliance

---

## 4. DATABASE DESIGN (Key Tables)

```sql
-- Core tables needed (simplified schema)
users (id, name, email, password, role, status, verification_status, suspended_at, archived_at, ...)
patients (id, user_id FK UNIQUE, name, phone, age, gender, condition, walk_in_code, archived_at, ...)
doctors (id, user_id FK UNIQUE, specialty, experience, license_number, license_authority, fee, bio, verification_status, archived_at, ...)
pharmacists (id, user_id FK UNIQUE, license_number, license_authority, ...)
specialties (id, name, ...)
doctor_schedules (id, doctor_id FK, day_of_week, start_time, end_time, slot_duration, ...)
doctor_leaves (id, doctor_id FK, start_date, end_date, reason, ...)
appointments (id, patient_id FK, doctor_id FK, date, start_time, end_time, status, visit_reason, rejection_reason, reschedule_count, ...)
prescriptions (id, patient_id FK, doctor_id FK, status, clinical_notes, pharmacy_notes, safety_snapshot JSON, ...)
prescription_items (id, prescription_id FK, medicine_id FK, dosage, schedule, duration, quantity, ...)
medicines (id, name, generic_name, category, unit_price, stock_quantity, min_stock_threshold, expiry_date, batch_number, side_effects, archived_at, ...)
inventory_ledger (id, medicine_id FK, type, quantity_change, previous_qty, new_qty, actor_id, reference, ...)
invoices (id, patient_id FK, pharmacist_id FK, prescription_id FK nullable, subtotal, discount, net_total, currency_code, payment_method, payment_status, ...)
invoice_items (id, invoice_id FK, medicine_id FK, quantity, unit_price, line_total, ...)
reviews (id, patient_id FK, doctor_id FK, appointment_id FK UNIQUE, rating, comment, moderation_status, ...)
notifications (id, user_id FK, type, message, data JSON, read_at, ...)
activity_log (id, actor_id FK, actor_role, action, target_type, target_id, detail JSON, ...)
system_settings (id, key, value, ...)
emergency_rules (id, symptom_keywords, severity, action_directive, is_active, ...)
symptom_specialty_map (id, symptom_category, specialty_id FK, ...)
faqs (id, question, answer, category, keywords, is_published, sort_order, ...)
pending_approvals (id, user_id FK, requested_role, license_details JSON, review_reason, reviewer_id, ...)
patient_allergies (id, patient_id FK, substance, allergy_type, severity, reaction_notes, ...)
patient_medications (id, patient_id FK, medicine_name, dosage, frequency, start_date, ...)
drug_interactions (id, medicine_a_id FK, medicine_b_id FK, severity, description, ...)
password_resets (id, email, token_hash, expires_at, consumed_at, ...)
symptom_check_logs (id, patient_id FK, symptoms JSON, ai_response JSON, emergency_triggered, ...)
```

---

## 5. DEVELOPMENT PLAN — PHASES & SPRINTS

### Phase 0: Foundation (Week 1)
| Sprint | Tasks |
|--------|-------|
| **Sprint 0.1** | Laravel installation, database schema migration, seeders (specialties, sample medicines, emergency rules, FAQs) |
| **Sprint 0.2** | Authentication system (Sanctum sessions, role gates, middleware, brute-force protection) |
| **Sprint 0.3** | Base layout (Bootstrap 5 responsive sidebar, navigation, guest header, footer), global currency/timezone config loader |
| **Sprint 0.4** | Activity log system, notification system (database + Reverb real-time), global error/retry handler |

### Phase 1: Guest & Public Features (Week 2)
| Sprint | Tasks (from doc sections) |
|--------|--------------------------|
| **Sprint 1.1** | `guestPublicAccessAndSessionGuard()` — Guest shell, route guards, public read views |
| **Sprint 1.2** | `guestRegisterAccount()` — Multi-role registration, role-specific forms, atomic transaction |
| **Sprint 1.3** | `guestLogin()` + `guestForgotAndResetPassword()` — Unified login, secure password reset |
| **Sprint 1.4** | `guestBrowseDoctorDirectory()` + `guestSearchAndFilterDoctors()` — Doctor grid, URL-synced filters |
| **Sprint 1.5** | `guestViewDoctorPublicProfile()` — Public profile with reviews & schedule |
| **Sprint 1.6** | `guestViewEmergencyInfoAndAlerts()` — Emergency page with static fallback |
| **Sprint 1.7** | `guestChatWithFaqChatbot()` — FAQ chatbot with emergency red-flag interceptor |
| **Sprint 1.8** | `guestViewPendingApprovalScreen()` — Pending approval holding page |

### Phase 2: Admin Core (Week 3)
| Sprint | Tasks (from doc sections) |
|--------|--------------------------|
| **Sprint 2.1** | `adminAuthAndLogout()` + `adminManageProfileAndSecurity()` — Admin auth gate, profile |
| **Sprint 2.2** | `adminLoadMasterDashboard()` + `adminViewSystemOverview()` — KPIs, operational health |
| **Sprint 2.3** | `adminManageUsers()` + `adminAssignAndChangeUserRoles()` — User management, role changes |
| **Sprint 2.4** | `adminApproveOrRejectPendingSignups()` — Approval queue |
| **Sprint 2.5** | `adminVerifyDoctorCredentials()` — Doctor credential verification workspace |
| **Sprint 2.6** | `adminAddDoctorRecord()` + `adminEditDoctorRecord()` + `adminRemoveOrArchiveDoctor()` — Doctor CRUD |
| **Sprint 2.7** | `adminRegisterPatientRecord()` + `adminEditPatientRecord()` + `adminRemoveOrArchivePatient()` — Patient CRUD |
| **Sprint 2.8** | `adminOperatePatientPortal()` — Patient search with prescription/billing history |

### Phase 3: Admin Advanced (Week 4)
| Sprint | Tasks |
|--------|-------|
| **Sprint 3.1** | `adminViewAndManageAllAppointments()` — Global appointment oversight |
| **Sprint 3.2** | `adminViewAllClinicalAndBillingRecords()` — All prescriptions, inventory, invoices |
| **Sprint 3.3** | `adminConfigureRevenuePercentage()` — Revenue percentage settings |
| **Sprint 3.4** | `adminViewReportsAndAnalytics()` + `adminExportAnalyticsReports()` — Analytics + CSV/PDF export |
| **Sprint 3.5** | `adminViewFilteredActivityLog()` — Immutable activity log viewer |
| **Sprint 3.6** | `adminManageChatbotFaqs()` — FAQ knowledge base manager |
| **Sprint 3.7** | `adminModerateDoctorReviews()` — Review moderation queue |
| **Sprint 3.8** | `adminManageEmergencyRulesAndSpecialtyMapping()` — Emergency rules + symptom-specialty mapping |
| **Sprint 3.9** | `adminManageGlobalSystemSettings()` — Global system settings (currency, timezone, locale) |
| **Sprint 3.10** | `adminManageDataExportImportAndBackup()` — Data management workspace |

### Phase 4: Patient Portal (Week 5)
| Sprint | Tasks |
|--------|-------|
| **Sprint 4.1** | `patientSessionLoginAndLogout()` + `patientRegisterAndLinkRecord()` — Patient session lifecycle |
| **Sprint 4.2** | `patientManageProfile()` + `patientChangePassword()` + `patientManageSettings()` — Profile & settings |
| **Sprint 4.3** | `patientManageAllergiesAndMedications()` — Clinical safety profile |
| **Sprint 4.4** | `patientBrowseDoctorsWithBookingContext()` + `patientSearchAndFilterDoctorsAuthenticated()` — Reuse guest doctor directory |
| **Sprint 4.5** | `patientBookAppointment()` + `patientCancelAppointment()` + `patientRescheduleAppointment()` — Booking workflow |
| **Sprint 4.6** | `patientViewAppointmentHistoryAndStatus()` — Appointment tracker |
| **Sprint 4.7** | `patientViewOwnPrescriptions()` + `patientDownloadPrescriptionPdf()` — Prescription viewing |
| **Sprint 4.8** | `patientViewOwnBills()` + `patientSearchOwnInvoiceById()` + `patientDownloadInvoicePdf()` — Billing |
| **Sprint 4.9** | `patientReceiveNotifications()` — Notification center |
| **Sprint 4.10** | `patientSubmitDoctorReviewAndRating()` — Review submission |
| **Sprint 4.11** | `patientLaunchAuthenticatedFaqChatbot()` — Extended FAQ chatbot |

### Phase 5: Patient AI Features (Week 6)
| Sprint | Tasks |
|--------|-------|
| **Sprint 5.1** | `patientCheckSymptomsWithAi()` — AI symptom checker with 2-stage pipeline |
| **Sprint 5.2** | `patientGetSpecialistRecommendation()` — Specialist mapping |
| **Sprint 5.3** | `patientShowSymptomEmergencyAlert()` — Emergency alert interceptor |

### Phase 6: Doctor Portal (Week 7)
| Sprint | Tasks |
|--------|-------|
| **Sprint 6.1** | `doctorVerifiedLoginAndLogout()` + `doctorManageProfile()` + `doctorManageSettings()` — Doctor auth, profile, settings |
| **Sprint 6.2** | `doctorManageScheduleAndLeave()` — Schedule builder |
| **Sprint 6.3** | `doctorViewAppointmentRequests()` + `doctorRespondToAppointmentRequest()` — Appointment management |
| **Sprint 6.4** | `doctorLoadDashboardMetrics()` — Doctor dashboard |
| **Sprint 6.5** | `doctorRegisterWalkInPatient()` — Walk-in patient registration |
| **Sprint 6.6** | `doctorViewPatientListAndDetails()` + `doctorViewPatientFullHistory()` + `doctorSearchPatients()` — Patient management |
| **Sprint 6.7** | `doctorSearchMedicineWithLiveStock()` + `doctorViewMedicineSideEffects()` — Medicine reference |
| **Sprint 6.8** | `doctorCheckPatientAllergyConflict()` + `doctorCheckDrugToDrugInteraction()` — Safety checks |

### Phase 7: Doctor Prescribing & AI (Week 8)
| Sprint | Tasks |
|--------|-------|
| **Sprint 7.1** | `doctorCreateAndSavePrescription()` + `doctorAttachPrescriptionClinicalNotes()` — Prescription builder |
| **Sprint 7.2** | `doctorCheckMedicineSafetyWithAi()` — AI medicine safety |
| **Sprint 7.3** | `doctorViewPrescriptionHistory()` + `doctorSearchPrescriptions()` + `doctorDownloadPrescriptionPdf()` — Prescription history |
| **Sprint 7.4** | `doctorViewOwnReviewsAndRatings()` + `doctorReceiveNotifications()` — Reviews & notifications |

### Phase 8: Pharmacist Portal (Week 9)
| Sprint | Tasks |
|--------|-------|
| **Sprint 8.1** | `pharmacistApprovedLoginAndLogout()` + `pharmacistManageSettings()` — Auth & settings |
| **Sprint 8.2** | `pharmacistLoadDashboardMetrics()` — Pharmacy dashboard |
| **Sprint 8.3** | `pharmacistViewPendingPrescriptions()` + `pharmacistVerifyPreDispenseSafetyWarnings()` — Pending queue & safety gate |
| **Sprint 8.4** | `pharmacistViewMedicineInventory()` + `pharmacistAddNewMedicine()` + `pharmacistEditOrArchiveMedicine()` — Inventory management |
| **Sprint 8.5** | `pharmacistRestockMedicineBatch()` — Restock workflow |
| **Sprint 8.6** | `pharmacistViewStockStatusAndAlerts()` + `pharmacistViewExpiryDateAlerts()` — Stock & expiry alerts |
| **Sprint 8.7** | `pharmacistCreatePatientBill()` — Invoice builder |
| **Sprint 8.8** | `pharmacistAutoDeductStockAndDischargePatient()` — Atomic ACID transaction engine |
| **Sprint 8.9** | `pharmacistGenerateAndDownloadInvoicePdf()` + `pharmacistViewAndSearchInvoices()` + `pharmacistUpdateInvoicePaymentStatus()` — Invoice management |
| **Sprint 8.10** | `pharmacistTrackClinicalWorkflow()` + `pharmacistReceiveNotifications()` — Workflow tracker & notifications |

### Phase 9: Integration Testing & Polish (Week 10)
| Sprint | Tasks |
|--------|-------|
| **Sprint 9.1** | End-to-end workflow tests (registration → booking → prescription → dispensing → billing → discharge) |
| **Sprint 9.2** | Security audit (role gates, session revocation, CSRF, XSS, SQL injection tests) |
| **Sprint 9.3** | Responsive testing across all breakpoints (360px, 640px, 768px, 1024px, 1440px) |
| **Sprint 9.4** | WCAG 2.1 AA accessibility audit (screen readers, keyboard navigation, contrast ratios) |
| **Sprint 9.5** | Performance optimization (query indexing, eager loading, caching, asset optimization) |
| **Sprint 9.6** | Bug fixes, edge cases, final documentation |

---

## 6. LARAVEL PROJECT STRUCTURE

```
medipath/
├── app/
│   ├── Models/
│   │   ├── User.php
│   │   ├── Patient.php
│   │   ├── Doctor.php
│   │   ├── Pharmacist.php
│   │   ├── Appointment.php
│   │   ├── Prescription.php
│   │   ├── PrescriptionItem.php
│   │   ├── Medicine.php
│   │   ├── Invoice.php
│   │   ├── InvoiceItem.php
│   │   ├── Review.php
│   │   ├── Notification.php
│   │   ├── ActivityLog.php
│   │   ├── SystemSetting.php
│   │   ├── Faq.php
│   │   ├── EmergencyRule.php
│   │   ├── SymptomSpecialtyMap.php
│   │   ├── DrugInteraction.php
│   │   ├── PatientAllergy.php
│   │   ├── PatientMedication.php
│   │   ├── InventoryLedger.php
│   │   ├── DoctorSchedule.php
│   │   ├── DoctorLeave.php
│   │   └── PendingApproval.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   ├── Admin/
│   │   │   ├── Patient/
│   │   │   ├── Doctor/
│   │   │   ├── Pharmacist/
│   │   │   └── Guest/
│   │   ├── Middleware/
│   │   │   ├── RoleGate.php
│   │   │   ├── VerifiedDoctor.php
│   │   │   ├── ApprovedPharmacist.php
│   │   │   ├── PendingStatusOnly.php
│   │   │   └── PreventSelfAction.php
│   │   └── Resources/
│   ├── Services/
│   │   ├── AiSymptomChecker.php
│   │   ├── AiMedicineSafety.php
│   │   ├── EmergencyRuleEngine.php
│   │   ├── AllergyConflictChecker.php
│   │   ├── DrugInteractionChecker.php
│   │   ├── PrescriptionPdfGenerator.php
│   │   ├── InvoicePdfGenerator.php
│   │   ├── AnalyticsExporter.php
│   │   ├── CsvImporter.php
│   │   └── RevenueCalculator.php
│   ├── Events/
│   ├── Listeners/
│   ├── Notifications/
│   └── Rules/
├── resources/
│   ├── js/
│   │   ├── Components/        (React components)
│   │   ├── Pages/
│   │   └── Hooks/
│   ├── views/                 (Blade templates for non-React pages)
│   └── sass/
├── routes/
│   ├── web.php
│   ├── api.php
│   └── channels.php
├── database/
│   ├── migrations/
│   └── seeders/
└── tests/
    ├── Feature/
    └── Unit/
```

---

## 7. FIGMA MAKE — UI/UX DESIGN GENERATION PROMPT

---

### FIGMA MAKE PROMPT — MediPath Full System

Copy the entire prompt below and use it in Figma Make (or the Figma plugin) to generate the UI designs:

```
Design a complete, responsive healthcare web application called "MediPath: AI-Powered Doctor Appointment and Medicine Safety System" with 5 role-based dashboards. Use a clean, modern medical UI style with calming blues (#2563EB primary, #1E40AF dark, #DBEAFE light), white backgrounds, and green (#059669) for success/verified states. All designs must be mobile-responsive (360px to 1440px).

GLOBAL COMPONENTS:
1. A medical-themed landing page (Guest view) with:
   - Hero section: "Your Health, Our Priority — AI-Powered Appointments & Medicine Safety"
   - Search bar to find doctors by name/specialty
   - Emergency hotline banner at top: "Emergency? Call 911 or visit nearest ER — Do not wait for online booking"
   - 3-column grid of verified doctors (photo, name, specialty, rating stars, fee in BDT/৳, "View Profile" button) — stacks to 2 cols on tablet, 1 col on mobile
   - Floating FAQ chatbot icon (bottom-right) that opens a chat drawer
   - Navbar: [MediPath Logo] | Find Doctors | Emergency Info | Login | Register — collapses to hamburger on mobile

2. Login page:
   - Centered card with email + password fields, password visibility toggle, "Forgot Password?" link, "Login" button
   - Below: "Don't have an account? Register"

3. Registration page:
   - Multi-step form: Step 1: Name, Email, Phone, Password, Confirm Password, Role (Patient/Doctor/Pharmacist radio buttons)
   - Step 2 (if Doctor/Pharmacist): License Number, Issuing Authority, Years of Experience, Specialty dropdown, Upload Credential Document
   - Step 2 (if Patient): Age, Gender, Optional Walk-in Claim Code
   - Step 3: Review & Submit
   - Clear inline validation messages below each field

ADMIN DASHBOARD (sidebar + content):
- Left sidebar (dark #1E293B): Logo, nav items with icons — Dashboard, User Approvals, Doctor Verification, Doctors, Patients, Appointments, Revenue & Analytics, Chatbot FAQs, Reviews, Emergency & AI Rules, System Settings, Data Management, Security — collapses to slide-over drawer on mobile
- Dashboard content: 5 KPI cards row (Total Active Patients, Verified Doctors, Prescriptions Issued, Active Medicines, Clinic Revenue) with skeleton loaders, date range toggles (Today/7D/30D/Year)
- System Overview panel: Active Doctors on Duty, Active Patients, Pending Prescriptions, Low Stock Alerts count, Pending Sign-Ups badge with drill-down links
- Tables with pagination, search bars, filter dropdowns, status badges (Active=green, Pending=yellow, Suspended=red, Archived=gray)
- Approval Queue: Cards showing applicant name, email, role, date, license preview, Approve/Reject buttons
- Doctor Verification: Table with doctors, license viewer modal, Approve/Reject/Revoke actions
- Revenue Analytics: Charts (bar/line), sortable tables, date range picker, CSV/PDF export buttons
- Confirm dialogs for destructive actions (typed confirmation + password re-entry for Danger Zone)
- Activity log: paginated feed with role badges, action labels, timestamps, search/filter

PATIENT DASHBOARD:
- Sidebar: My Appointments, Find Doctors, My Prescriptions, My Bills, AI Symptom Checker, My Reviews, Allergies & Medications, Notifications (bell badge), Settings
- Appointment booking: Date picker (gray out past dates), time slot chips (green=available, gray=booked), Doctor info card (name, specialty, fee), "Confirm Booking" button
- Appointment history: Status tabs (All/Pending/Accepted/Rejected/Cancelled/Completed), action buttons per status (Cancel, Reschedule, Leave Review, Download Prescription)
- AI Symptom Checker: Structured form — body region selector, symptom keywords, description textarea, duration, severity (mild/moderate/severe), consent toggle — prominent medical disclaimer at top
- Emergency alert panel (red theme): Urgent warning icon, symptom trigger, one-tap call buttons, ER directions
- My Prescriptions: Cards with prescription ID, date, doctor, medicines list, Download PDF button
- My Bills: Invoice cards with ID, date, itemized list, subtotal/discount/net total, payment status badge, Download PDF button
- Doctor review: Star rating selector (1-5), review text area, submit button

DOCTOR DASHBOARD:
- Sidebar: Dashboard, Schedule, Appointment Requests, Patients, Medicines, Prescriptions, Reviews, Notifications, Settings
- Dashboard: Today's patients count, total prescriptions, pending requests, average rating — appointment timeline, request queue with Accept/Reject buttons
- Schedule builder: Weekly grid (Mon-Sun), add time blocks (start/end time, slot duration 15/20/30 min), add leave dates — mobile: stacked day cards
- Appointment Requests: Filter tabs (Pending/Today/Upcoming/Completed/Cancelled), Accept/Reject with rejection reason modal, Mark Completed
- Patient detail view: Demographics, high-visibility Allergy Alert strip (red/yellow), current medications list, tabbed history (Prescriptions/Appointments/Billing)
- Prescription builder: Patient selector, medicine search with live stock indicator (green=in stock, red=out of stock, orange=low), dosage/schedule/duration fields, allergy conflict warnings (red banner), drug interaction warnings (yellow banner with severity), AI safety button, clinical notes, pharmacy notes, Save Prescription button
- Walk-in patient registration form

PHARMACIST DASHBOARD:
- Sidebar: Dashboard, Pending Prescriptions, Inventory, Stock Alerts, Expiry Alerts, Billing, Workflow Tracker, Notifications, Settings
- Dashboard: Pending prescriptions count, dispensed today count + total billed, low stock alerts count — oldest pending prescriptions queue, urgent stock alerts
- Pending Prescriptions queue: Patient name, doctor, medicines with stock availability indicator, Review & Dispense button
- Pre-Dispense Safety Gate (modal): Allergy conflicts, drug interactions, expired batch blocks, insufficient stock warnings — pharmacist verification checkbox + notes before unlocking dispense
- Inventory: Searchable, filterable table (name, category, stock, price, expiry, status), Add Medicine/Edit/Restock buttons
- Billing: Patient selector (from prescription), medicine rows with quantity/price, discount field, payment method dropdown, live subtotal/discount/net total preview
- Workflow Tracker: 6 stages (Registration → Prescription → Stock Check → Dispense → Billing → Complete/Discharged), horizontal stepper (vertical on mobile), clickable stages

ALL PAGES MUST INCLUDE:
- Skeleton loaders (pulsing gray placeholders) while data loads
- Empty states with illustrations and helpful messages when no data exists
- Error states with "Something went wrong" message and prominent "Retry" button
- Loading spinners on buttons during API calls
- Toast notifications for success/error feedback
- Mobile: bottom navigation bar for key actions, full-width cards, 44x44px minimum touch targets
- Dark mode toggle in settings (light/dark/system)
- WCAG 2.1 AA: sufficient color contrast, skip-to-content link, keyboard-navigable, screen reader labels
- Currency formatted as ৳ (BDT) — never hardcoded $ signs
- "MediPath" branding with a medical cross/healthcare logo
```

---

## 8. KEY TECHNICAL DECISIONS SUMMARY

| Decision | Choice | Reason |
|----------|--------|--------|
| SPA vs MPA | Hybrid (React for interactive sections, Blade for static) | Best of both — React where interactivity matters, Blade for SEO-friendly public pages |
| State Management | React Context + Server State (TanStack Query) | Avoids Redux complexity; TanStack Query handles caching, refetching, retries |
| Real-Time | Laravel Reverb (self-hosted WebSocket) | No third-party costs; native Laravel integration |
| PDFs | DomPDF | Server-side generation, shared templates across roles (as docs mandate) |
| AI | OpenAI API (GPT-4o-mini for cost) or local LLM fallback | Structured prompts with system guardrails; graceful fallback when AI is unavailable |
| File Uploads | Laravel Filesystem (local disk for dev, S3 for prod) | Credential documents stored privately, not publicly accessible |
| Search | MySQL Full-Text Index + Laravel Scout | Fast parameterized search without external services |
| Caching | Redis (sessions, settings, doctor directory cache) | Low-latency; settings TTL invalidation on Admin update |
| Containerization | Laravel Sail (Docker) | Consistent dev environment across team members |

---

## 9. IMMEDIATE NEXT STEPS

1. **Design Figma screens** using the prompt in Section 7 above — generate at least 15-20 key screens
2. **Set up Laravel project**: `composer create-project laravel/laravel medipath`
3. **Configure Bootstrap 5 + React**: Install via Vite, set up Inertia.js OR standalone React frontend
4. **Create database migrations**: Start with `users`, `patients`, `doctors`, `pharmacists` tables
5. **Build authentication system**: Laravel Sanctum session auth with role gates
6. **Implement Admin dashboard**: Start from the top (KPIs, approvals, user management)
7. **Iterate through phases**: Follow the sprint plan in Section 5

---

*Document prepared for CSE-479 Lab Project — MediPath System*
*Generated on: November 2026*