# LeaveDesk — Session Handoff

**Purpose of this document**: context primer for a fresh Claude Code session picking up this project mid-stream. Read this first, then `CLAUDE.md` (project conventions) and `PROGRESS.md` (full session-by-session log) for anything not covered here.

**Repo**: `d:\LaravelProjects\leavedesk` | **Current branch**: `phase-2` (created specifically so this work is revertible without touching `main`) | **Working tree**: clean, 3 commits ahead of `main`.

---

## Tech Stack

- **Laravel 12** (`laravel/framework: ^12.0`)
- **Livewire 3** (`livewire/livewire: ^3.0`) — all interactive UI is full-page Livewire components, not traditional controllers+Blade
- **Alpine.js** (bundled with Breeze, used for dropdowns/nav toggles)
- **Tailwind CSS 3** (`@tailwindcss/forms`, `@tailwindcss/vite`)
- **Vite** for asset bundling
- **MySQL** (dev database name: `leavedesk`) — this is the actual verification database throughout; SQLite in-memory is only used by the automated test suite (`phpunit.xml`)
- **Laravel Breeze** (Blade stack) for auth scaffolding — registration was deliberately removed (HR adds employees manually, no public sign-up)
- Auth: session-based, standard Breeze; no API/Sanctum in use

No FullCalendar or any calendar JS library is installed yet — that's the next task (see bottom).

## Our Strict Architectural Rules

These are non-negotiable conventions established from the start of this project (see `CLAUDE.md`) and enforced consistently across every file built so far:

1. **Query-builder only for database writes/reads in business logic.** All services (`app/Services/*.php`) use `DB::table(...)` exclusively for querying and mutating data — no Eloquent relationship traversal (`$model->relation()->...`), no `Model::where(...)->update(...)` chains. Eloquent models exist (`app/Models/*.php`) but are used narrowly and deliberately:
   - Where Laravel's own framework mechanics *require* a real Eloquent instance (e.g., `Gate::forUser($approver)->authorize('approve', $leaveRequest)` needs a `LeaveRequest` model, not a stdClass; `->notify()` requires a `Notifiable` model instance).
   - Simple primary-key lookups (`Model::find($id)`) are treated as acceptable — they're not "relational queries," just a fetch-by-id that happens to return a typed object.
   - Everything else — joins, filtering, listing, aggregation, upserts — is explicit `DB::table()` code. This shows up as `leftJoin`/`join` calls throughout (e.g., `LeaveRequestService::pendingForApprover()`, `EmployeeDirectoryService::list()`).
   - **When touching this codebase further, default to query builder for any new data access.** Only reach for Eloquent when there's a specific framework-level reason (documented inline when it happens, e.g. `LeaveRequestService.php`'s comments around `LeaveRequest::find()`).

2. **Timezone enforcement — PKT (+05:00) end-to-end, not just in application code.** This was added in the most recent session because check-in/late-flag comparisons are time-of-day sensitive:
   - `config('app.timezone')` = `Asia/Karachi` (set via `APP_TIMEZONE` in `.env`, defaults there if unset — see `config/app.php`).
   - The MySQL connection itself is pinned to the same offset: `config/database.php`'s `mysql` connection has `'timezone' => env('DB_TIMEZONE', '+05:00')`. This issues a `SET time_zone` session command on connect, so `TIMESTAMP` columns round-trip consistently between PHP and MySQL.
   - **Fixed UTC offset (`+05:00`), not a named zone, on the MySQL side** — deliberate, because PKT has no DST and a fixed offset doesn't depend on MySQL's `mysql.time_zone_name` tables being populated (many installs don't have `mysql_tzinfo_to_sql` run).
   - Verified working: PHP `now()` and MySQL `SELECT @@session.time_zone` both report the PKT offset consistently.
   - **Any new time-of-day comparison logic must use `now()`/`CarbonImmutable::now()`** (which respect the configured app timezone) — never raw `date()` or hardcoded UTC assumptions.

3. **Strict typing everywhere**: `declare(strict_types=1);` at the top of every PHP file, typed properties/parameters/return types throughout. (A few untouched Breeze-scaffold files from the original install still lack this — not worth retrofitting unless you're already editing them for another reason.)

4. **Custom, fully-specified migrations** — no generic/auto-generated stubs. Every migration explicitly defines columns, types, constraints, indexes (see any file in `database/migrations/` for the pattern).

5. **No hard deletes for anything with history** (employees, departments, leave types). These use an `is_active` boolean flag instead — deactivate/reactivate via the HR admin UI, never delete. (Holidays are the one exception: they get real delete, since nothing references a holiday's id and there's no history to protect.)

6. **Authorization enforced in the service layer, not just the UI.** E.g. `LeaveRequestService::approve()`/`reject()` call `Gate::forUser($approver)->authorize(...)` internally — so it can't be bypassed by any future caller, not just the current Livewire component. This came from a real bug found mid-project (a policy existed but nothing actually invoked it).

7. **Verification habit**: every feature in this project has been verified three ways before being considered done — (a) automated feature tests, (b) `php artisan test` full suite green, (c) manual verification against the real MySQL dev database via `php artisan tinker` and/or real HTTP requests (`php artisan serve` + `curl`), not just trusting the test suite. Keep doing this for new work.

## Current Progress

### Phase 1 — Core Leave Management (complete)

Employees, departments, leave types, and leave balances (full data model + HR admin CRUD with active/inactive lifecycle, never hard-deleted). Online leave requests with balance checks at both apply-time and approval-time, half-day/multi-day support, overlap prevention. Manager approval workflow — approve/reject with a note, every decision timestamped with an approver, role-scoped access throughout (employee/manager/hr). Manager gets notified instantly (mail + database channel) on submission; employee gets notified of the outcome. A leadership auto-approve bypass exists (manager/HR submitting their own leave is instantly self-approved, no notification) — added per explicit request, beyond the spec's literal text. Carry-forward is fully implemented: unused days roll into the next year, capped at each leave type's configured max (uncapped if left blank), computed in `LeaveBalanceService::ensureBalanceForYear()`.

### Phase 2 — Attendance, sub-steps 1-4 (complete)

1. **Work schedule + holidays (HR admin)**: `work_schedule` table (single company-wide row — working days, start/end time, grace period) and a `holidays` table (unique date + name). Two HR-only admin screens mirroring the existing Employees/Departments/LeaveTypes CRUD pattern.
2. **Attendance check-in**: `attendances` table (employee, date, check-in/out timestamps, status, unique on `(user_id, date)`), employee-facing check-in/check-out page at `/attendance`, open to every role.
3. **Auto-link approved leave → attendance**: `LeaveRequestService::approve()` writes an `on_leave` attendance row for every *working* day in the approved range (skips weekends and configured holidays via `WorkScheduleService::isWorkingDay()`).
4. **Late-arrival flag**: `AttendanceService::checkIn()` compares the check-in time against `work_schedule.start_time + grace_minutes` and records `present` or `late` accordingly.

Along the way, a real correctness bug surfaced and got fixed: `LeaveRequestService::calculateTotalDays()` used to count every calendar day in a multi-day request, including weekends/holidays — so a request spanning a public holiday over-charged the employee's balance. Fixed using the same working-day logic that drives the attendance auto-link, so balance deduction and attendance now agree on what a "day of leave" means. A request whose entire range has zero working days is now rejected outright.

Key design decisions worth knowing if you touch this area again:
- `markOnLeave()` is idempotent (check-then-act, never a blind insert) and never touches `check_in_at`/`check_out_at` — so it's safe to call repeatedly and never clobbers real attendance data.
- `checkIn()` never downgrades an existing `on_leave` status back to `present`/`late` — if someone's on approved leave but still checks in (e.g. a half-day), `status` stays `on_leave` as the authoritative record, while the check-in timestamp is still recorded factually alongside it.

**91/91 tests passing** as of the last commit (`f85e206`).

## Next Task — Phase 2 Sub-step 5: FullCalendar team view

This is the immediate next goal. Scope, per the standing plan:

- **Manager-facing, read-only calendar** showing their team's approved leave and configured holidays.
- "Their team" = the same "assigned manager" scope already used by `LeaveRequestService::pendingForApprover()` — direct reports (`users.manager_id`) plus anyone in a department they head (`departments.manager_id`).
- Uses **FullCalendar JS** (per the original project spec's intended stack) — **not yet installed**; check `package.json` before assuming anything is wired up.
- This is a different shape of work than sub-steps 1-4: it's the first time this project needs a new frontend dependency, Vite bundling changes, and JS-to-Livewire interop for feeding calendar event data — treat it as its own focused pass rather than a same-pattern service extension.
- Follow the established verification habit: feature tests + real MySQL/HTTP checks (e.g. confirm a manager sees their team's entries and an unrelated manager doesn't see someone else's).

Read `PROGRESS.md`'s most recent entries for the full narrative if anything here is ambiguous.
