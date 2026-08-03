# LeaveDesk

**Employee leave & attendance management for small teams.**

LeaveDesk replaces WhatsApp- and Excel-based HR tracking with a single web app: employees apply for leave and check in online, managers approve from a real queue instead of a chat thread, and HR configures policy and pulls payroll-ready reports on demand — all with a full audit trail.

Built as an internship project at **Nimble Web Solutions** (July 2026), following the phased spec in `LeaveDesk_Project_Details.pdf`.

---

## Features

LeaveDesk implements all four delivery phases from the project spec.

**Core (Phase 1)**
- Role-based access for Employee / Manager / HR, each scoped to their own data, team, or company-wide view
- Leave types with configurable yearly allocation and carry-forward rules
- Leave requests validated against live balance at both submission and approval time
- Two-stage approval workflow (Manager → HR) with a full trail of who approved/rejected and when, plus a decision note
- Managers are auto-forwarded to HR for their own leave requests, since they can't approve themselves
- Email + in-app notifications on submission and decision (delivery failures are logged, never block the workflow)

**Attendance (Phase 2)**
- Daily check-in/check-out, with approved leave auto-written into the attendance record as the single source of truth
- Configurable work schedule (working days, shift times) and company holiday calendar drive lateness/absence calculation
- Manager team calendar view

**Reporting (Phase 3)**
- Scheduled automated HR reports
- Excel and PDF attendance/payroll exports (Laravel Excel + DomPDF)
- Role-appropriate dashboards

**Scale (Phase 4)**
- Multi-level (Manager → HR) approval chain
- GPS-geofenced check-in — employees must be within a configured radius of the office (Haversine distance check, no paid geolocation service)
- Payroll REST API (`GET /api/v1/payroll/summary`) for external accounting/payroll systems, secured with Sanctum bearer tokens

**Mobile API**

A companion Flutter app talks to LeaveDesk over a versioned, Sanctum-authenticated JSON API covering login, attendance check-in/out, leave requests and balances, and role-specific approval queues (Manager/HR) and HR admin (employees, departments, leave types, holidays, work schedule). See `routes/api.php`.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 (PHP 8.2+) |
| Frontend | Livewire 3 + Alpine.js, Blade |
| Styling | Tailwind CSS |
| Database | MySQL 8.0 |
| Auth | Laravel Breeze (session) + Laravel Sanctum (API tokens) |
| Reporting | Laravel Excel, DomPDF |
| Background work | Laravel Queue + Scheduler |
| Calendar UI | FullCalendar |

---

## Getting Started

### Requirements
- PHP 8.2+
- Composer
- Node.js + npm
- MySQL 8.0

### Setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Configure environment
cp .env.example .env
php artisan key:generate
```

Set your database credentials in `.env`. For local development, `MAIL_MAILER=log` writes outgoing mail to `storage/logs/laravel.log` (or point it at a local [Mailpit](https://github.com/axllent/mailpit) instance to view rendered emails in a browser).

```bash
# 3. Run migrations and seed demo data (departments, leave types, users)
php artisan migrate --seed

# 4. Build frontend assets
npm run build

# 5. Start the app
composer run dev
```

`composer run dev` runs the app server, queue worker, log viewer (`pail`), and Vite dev server concurrently. For production, use `npm run build` instead of the Vite dev server.

### Payroll API token

To issue an API token for external payroll/accounting integrations:

```bash
php artisan payroll:generate-token {email?}
```

Defaults to the first active HR/Admin user if no email is given.

---

## Testing

```bash
php artisan test
```

Tests are plain PHPUnit, split across `tests/Unit` and `tests/Feature`, and run against an in-memory SQLite database. Run `vendor/bin/pint` to format PHP code before committing.

---

## Project Structure

Business logic lives in service classes under `app/Services` (e.g. `LeaveRequestService`, `AttendanceService`, `LeaveBalanceService`), kept thin and reusable so both the Livewire web UI and the JSON API call the same code paths. Web screens are Livewire 3 full-page components under `app/Livewire`; the mobile-facing API lives under `app/Http/Controllers/Api`.

---

*Developed by Sayyad Ali Haider Sherazi — Nimble Web Solutions.*
