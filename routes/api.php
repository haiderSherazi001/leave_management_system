<?php

use App\Http\Controllers\Api\Admin\DepartmentController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\HolidayController;
use App\Http\Controllers\Api\Admin\LeaveTypeController;
use App\Http\Controllers\Api\Admin\WorkScheduleController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HrLeaveController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\ManagerLeaveController;
use App\Http\Controllers\Api\PayrollController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/payroll/summary', [PayrollController::class, 'summary']);

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::prefix('attendance')->group(function () {
        Route::get('/today', [AttendanceController::class, 'today']);
        Route::post('/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/check-out', [AttendanceController::class, 'checkOut']);
        Route::get('/history', [AttendanceController::class, 'history']);
    });

    Route::prefix('leave')->group(function () {
        Route::get('/types', [LeaveController::class, 'types']);
        Route::get('/balances', [LeaveController::class, 'balances']);
        Route::get('/preview-total-days', [LeaveController::class, 'previewTotalDays']);
        Route::post('/requests', [LeaveController::class, 'store']);
        Route::get('/requests', [LeaveController::class, 'history']);
        Route::get('/holidays/upcoming', [LeaveController::class, 'upcomingHolidays']);
    });

    Route::prefix('manager')->group(function () {
        Route::get('/leave-requests/pending', [ManagerLeaveController::class, 'pending']);
        Route::get('/leave-requests/history', [ManagerLeaveController::class, 'history']);
        Route::post('/leave-requests/{leaveRequestId}/approve', [ManagerLeaveController::class, 'approve']);
        Route::post('/leave-requests/{leaveRequestId}/reject', [ManagerLeaveController::class, 'reject']);
    });

    Route::prefix('hr')->group(function () {
        Route::get('/leave-requests/pending', [HrLeaveController::class, 'pending']);
        Route::get('/leave-requests/history', [HrLeaveController::class, 'history']);
        Route::post('/leave-requests/{leaveRequestId}/approve', [HrLeaveController::class, 'approve']);
        Route::post('/leave-requests/{leaveRequestId}/reject', [HrLeaveController::class, 'reject']);
    });

    Route::prefix('admin')->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::put('/employees/{id}', [EmployeeController::class, 'update']);
        Route::post('/employees/{id}/toggle-active', [EmployeeController::class, 'toggleActive']);

        Route::get('/departments', [DepartmentController::class, 'index']);
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::put('/departments/{id}', [DepartmentController::class, 'update']);
        Route::post('/departments/{id}/toggle-active', [DepartmentController::class, 'toggleActive']);

        Route::get('/leave-types', [LeaveTypeController::class, 'index']);
        Route::post('/leave-types', [LeaveTypeController::class, 'store']);
        Route::put('/leave-types/{id}', [LeaveTypeController::class, 'update']);
        Route::post('/leave-types/{id}/toggle-active', [LeaveTypeController::class, 'toggleActive']);

        Route::get('/holidays', [HolidayController::class, 'index']);
        Route::post('/holidays', [HolidayController::class, 'store']);
        Route::put('/holidays/{id}', [HolidayController::class, 'update']);
        Route::delete('/holidays/{id}', [HolidayController::class, 'destroy']);

        Route::get('/work-schedule', [WorkScheduleController::class, 'show']);
        Route::put('/work-schedule', [WorkScheduleController::class, 'update']);
    });
});
