<?php

use App\Http\Controllers\Admin\AttendanceExportController;
use App\Http\Controllers\Admin\AttendancePdfExportController;
use App\Livewire\Admin\CompanySettings;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Departments;
use App\Livewire\Admin\Employees;
use App\Livewire\Admin\Holidays;
use App\Livewire\Admin\LeaveApprovals;
use App\Livewire\Admin\LeaveTypes;
use App\Livewire\Admin\OfficeLocation;
use App\Livewire\Admin\WorkSchedule;
use App\Livewire\Attendance\CheckIn;
use App\Livewire\Dashboard;
use App\Livewire\Leave\ApprovalQueue;
use App\Livewire\Leave\MyRequests;
use App\Livewire\Leave\RequestForm;
use App\Livewire\Leave\TeamCalendar;
use App\Livewire\Notifications;
use App\Livewire\Profile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route(Auth::user()->homeRouteName());
    }

    return view('landing');
})->name('landing');

Route::get('/dashboard', Dashboard::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', Profile::class)->name('profile.edit');
    Route::get('/notifications', Notifications::class)->name('notifications.index');

    Route::get('/attendance', CheckIn::class)->name('attendance.check-in');

    Route::get('/leave/apply', RequestForm::class)->name('leave.apply');
    Route::get('/leave/my-requests', MyRequests::class)->name('leave.my-requests');
    Route::get('/leave/approvals', ApprovalQueue::class)->name('leave.approvals');
    Route::get('/leave/team-calendar', TeamCalendar::class)->name('leave.team-calendar');

    Route::get('/admin/dashboard', AdminDashboard::class)->name('admin.dashboard');
    Route::get('/admin/attendance/export', AttendanceExportController::class)->name('admin.attendance.export');
    Route::get('/admin/attendance/export-pdf', AttendancePdfExportController::class)->name('admin.attendance.export-pdf');
    Route::get('/admin/leave-approvals', LeaveApprovals::class)->name('admin.leave-approvals');
    Route::get('/admin/employees', Employees::class)->name('admin.employees');
    Route::get('/admin/departments', Departments::class)->name('admin.departments');
    Route::get('/admin/leave-types', LeaveTypes::class)->name('admin.leave-types');
    Route::get('/admin/work-schedule', WorkSchedule::class)->name('admin.work-schedule');
    Route::get('/admin/holidays', Holidays::class)->name('admin.holidays');
    Route::get('/admin/office-location', OfficeLocation::class)->name('admin.office-location');
    Route::get('/admin/company', CompanySettings::class)->name('admin.company');
});

require __DIR__.'/auth.php';
