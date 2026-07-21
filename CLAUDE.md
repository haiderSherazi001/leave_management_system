# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Vibe Coding Guidelines
We are building LeaveDesk based on the `LeaveDesk_Project_Details.pdf` spec. 

Do not strictly enforce overly rigid backend rules (like requiring manual query builders, `declare(strict_types=1)`, or avoiding Eloquent). Use standard, modern, and clean Laravel conventions. Use Eloquent ORM and its built-in relationship methods for queries and database writes wherever it makes the code cleaner and faster to write.

## What LeaveDesk is
A web app for small companies to manage employee leave requests and daily attendance — replacing WhatsApp/Excel-based HR tracking. 

Three user roles:
- **Employee** — applies for leave online, sees remaining leave balance, marks daily attendance/check-in, tracks request status.
- **Manager** — gets notified of requests, approves/rejects with a note, sees team calendar and attendance.
- **HR / Admin** — configures leave types & policies, sets holidays/work schedule, manages employees & departments, receives automated reports, exports payroll-ready attendance.

Core data entities: `Employee`, `Department`, `LeaveType`, `LeaveRequest`, `LeaveBalance`, `Attendance`, `Report`.

Key workflow rules from the spec:
- **Balance enforcement** — leave requests are checked against available balance at apply time and again at approval.
- **Approval trail** — every request stores who approved/rejected it and when.
- **Auto-linked attendance** — approved leave writes directly into the attendance record (single source of truth).
- **Schedule-aware** — weekends, holidays, and shift times affect attendance/lateness calculations.
- **Role-scoped access** — employees see only their own data, managers see their team, HR sees the whole company.

## Delivery Phases
Build features in this exact order:
1. **Phase 1: Core** (employees, leave types/balances, online leave requests, manager approval workflow).
2. **Phase 2: Attendance** (daily attendance marking, auto-linked leave, holidays & late flags, team calendar).
3. **Phase 3: Reporting** (automated scheduled HR reports, Excel/PDF exports, dashboards).
4. **Phase 4: Scale** (multi-level approvals, biometric/geo check-in, payroll integration).

## Tech Stack
- **Backend:** Laravel 11 (PHP 8.2).
- **Frontend:** Livewire 3 + Alpine.js, Tailwind CSS.
- **Database:** MySQL 8.0.
- **Auth:** Laravel Breeze + role permissions.
- **Features:** Laravel Scheduler/Queue, Laravel Excel + DomPDF, FullCalendar (JS).

## Common Commands
- `composer run dev` — runs `php artisan serve`, `queue:listen`, `pail` (logs), and `npm run dev` (Vite) concurrently
- `npm run build` — production frontend build
- `php artisan test` — run the full test suite
- `vendor/bin/pint` — format PHP code (Laravel Pint)

## Testing Notes
Plain PHPUnit is used (no Pest). Tests are split into `tests/Unit` and `tests/Feature`. The testing environment uses in-memory SQLite.