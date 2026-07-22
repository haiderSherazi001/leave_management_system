<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeaveController;
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
});
