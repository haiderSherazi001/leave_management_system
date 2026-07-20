<?php

use App\Http\Controllers\Admin\AttendanceExportController;
use App\Http\Controllers\ProfileController;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Departments;
use App\Livewire\Admin\Employees;
use App\Livewire\Admin\Holidays;
use App\Livewire\Admin\LeaveTypes;
use App\Livewire\Admin\WorkSchedule;
use App\Livewire\Attendance\CheckIn;
use App\Livewire\Leave\ApprovalQueue;
use App\Livewire\Leave\MyRequests;
use App\Livewire\Leave\RequestForm;
use App\Livewire\Leave\TeamCalendar;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    Route::get('/attendance', CheckIn::class)->name('attendance.check-in');

    Route::get('/leave/apply', RequestForm::class)->name('leave.apply');
    Route::get('/leave/my-requests', MyRequests::class)->name('leave.my-requests');
    Route::get('/leave/approvals', ApprovalQueue::class)->name('leave.approvals');
    Route::get('/leave/team-calendar', TeamCalendar::class)->name('leave.team-calendar');

    Route::get('/admin/dashboard', Dashboard::class)->name('admin.dashboard');
    Route::get('/admin/attendance/export', AttendanceExportController::class)->name('admin.attendance.export');
    Route::get('/admin/employees', Employees::class)->name('admin.employees');
    Route::get('/admin/departments', Departments::class)->name('admin.departments');
    Route::get('/admin/leave-types', LeaveTypes::class)->name('admin.leave-types');
    Route::get('/admin/work-schedule', WorkSchedule::class)->name('admin.work-schedule');
    Route::get('/admin/holidays', Holidays::class)->name('admin.holidays');
});

require __DIR__.'/auth.php';
