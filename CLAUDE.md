# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project status

This repo starts as an unmodified Laravel skeleton. As of this writing there is no app-specific code yet
beyond the default `Controller`, `User` model, `AppServiceProvider`, the default welcome route, and the
three stock migrations (users, cache, jobs). **`LeaveDesk_Project_Details.pdf`** at the repo root is the
product spec (v1.0) and is the source of truth for what to build — read it before starting any feature
work, since none of the intent below can be derived from the code itself yet.

## What LeaveDesk is

A web app for small companies to manage employee leave requests and daily attendance — replacing
WhatsApp/Excel-based HR tracking. Three user roles:

- **Employee** — applies for leave, sees leave balance, marks daily attendance/check-in, tracks request status.
- **Manager** — gets notified of requests, approves/rejects with a note, sees team calendar and attendance.
- **HR / Admin** — configures leave types & policies, sets holidays/work schedule, manages employees &
  departments, receives automated reports, exports payroll-ready attendance.

Core data entities: `Employee`, `Department`, `LeaveType`, `LeaveRequest`, `LeaveBalance`, `Attendance`, `Report`.

Key workflow rules from the spec:
- **Balance enforcement** — leave requests are checked against available balance at apply time and again at approval.
- **Approval trail** — every request stores who approved/rejected it and when.
- **Auto-linked attendance** — approved leave writes directly into the attendance record (single source of truth; a day is never missed or double-counted).
- **Schedule-aware** — weekends, holidays, and shift times affect attendance/lateness calculations.
- **Role-scoped access** — employees see only their own data, managers see their team, HR sees the whole company.

Delivery phases (build in this order): **Phase 1 Core** (employees, leave types/balances, leave requests,
manager approval) → **Phase 2 Attendance** (daily attendance, auto-linked leave, holidays/late flags, team
calendar) → **Phase 3 Reporting** (scheduled HR reports, Excel/PDF export, dashboards) → **Phase 4 Scale**
(multi-level approvals, WhatsApp/SMS alerts, biometric/geo check-in, payroll integration).

## Intended tech stack

The spec calls for Livewire 3 + Alpine.js, Laravel Breeze (auth/roles), MySQL, Laravel Excel + DomPDF,
FullCalendar (JS), and the Laravel Scheduler with a database queue for report generation. Only bare
`laravel/framework` + `laravel/tinker` are installed at the moment — install the rest as each phase of
work requires it rather than all upfront.

## Common commands

- `composer install` / `npm install` — install dependencies
- `composer run dev` — runs `php artisan serve`, `queue:listen`, `pail` (logs), and `npm run dev` (Vite) concurrently
- `php artisan serve` / `npm run dev` — run backend/frontend individually
- `npm run build` — production frontend build
- `composer test` or `php artisan test` — run the full test suite (clears config cache first)
- `php artisan test --filter=TestName` or `php artisan test tests/Feature/ExampleTest.php` — run a single test
- `php artisan migrate` — run migrations
- `vendor/bin/pint` — format PHP code (Laravel Pint, already in `require-dev`)

## Testing notes

Plain PHPUnit is used (no Pest). Tests are split into `tests/Unit` and `tests/Feature` per `phpunit.xml`.
The testing environment uses in-memory SQLite, array cache/session drivers, and a sync queue — no external
services need to be running to test.

## Coding guidelines

- Enforce strict type hinting across all PHP files: use `declare(strict_types=1);` and typed
  properties/parameters/return types everywhere.
- Always write custom, detailed database migrations — don't leave generic/auto-generated stubs;
  every migration should explicitly define its columns, types, constraints, and indexes.
- Prefer manual relational queries (explicit joins / query builder) over Eloquent's "magic"
  relationship methods when writing queries.
