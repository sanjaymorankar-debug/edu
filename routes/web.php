<?php

use App\Http\Controllers\ComplaintEvidenceController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/', 'schools.index')->name('home');
Volt::route('schools', 'schools.index')->name('schools.index');
Volt::route('schools/register', 'schools.register')->name('schools.register');
Volt::route('schools/{school}', 'schools.show')->name('schools.show');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('dashboard/parent', 'dashboards.parent')->name('dashboard.parent')->middleware('role:parent');
    Volt::route('dashboard/student', 'dashboards.student')->name('dashboard.student')->middleware('role:student');
    Volt::route('dashboard/teacher', 'dashboards.teacher')->name('dashboard.teacher')->middleware('role:teacher');
    Volt::route('dashboard/school', 'dashboards.school')->name('dashboard.school')->middleware('role:school_admin');
    Volt::route('dashboard/district', 'dashboards.district')->name('dashboard.district')->middleware('role:district_officer');
    Volt::route('dashboard/state', 'dashboards.state')->name('dashboard.state')->middleware('role:state_officer');
    Volt::route('dashboard/national', 'dashboards.national')->name('dashboard.national')->middleware('role:national_admin');
    Volt::route('dashboard/researcher', 'dashboards.researcher')->name('dashboard.researcher')->middleware('role:researcher');
    Volt::route('dashboard/admin', 'dashboards.admin')->name('dashboard.admin')->middleware('role:system_admin');
    Volt::route('dashboard/placeholder', 'dashboards.placeholder')->name('dashboard.placeholder');

    Volt::route('onboarding', 'onboarding.index')->name('onboarding')->middleware('role:parent|student|teacher');

    Volt::route('complaints/create', 'complaints.create')->name('complaints.create')->middleware('role:parent|student');
    Volt::route('complaints/{complaint}', 'complaints.show')->name('complaints.show');
    Volt::route('feedback/create/{school}', 'feedback.create')->name('feedback.create')->middleware('role:parent|student');

    Volt::route('teachers/{teacher}/feedback', 'teacher-feedback.create')->name('teacher-feedback.create')->middleware('role:parent|student');

    Volt::route('retaliation/report', 'retaliation.create')->name('retaliation.create')->middleware('role:parent|student');
    Volt::route('retaliation/{retaliationReport}', 'retaliation.show')->name('retaliation.show');

    Route::get('complaints/{complaint}/evidence/{evidence}', ComplaintEvidenceController::class)
        ->name('complaints.evidence.download');

    Route::view('profile', 'profile')->name('profile');

    Route::middleware('role:system_admin')->prefix('admin')->name('admin.')->group(function () {
        Volt::route('rating-weights', 'admin.rating-weights')->name('rating-weights');
        Volt::route('categories', 'admin.categories')->name('categories');
        Volt::route('audit-log', 'admin.audit-log')->name('audit-log');
    });
});

require __DIR__.'/auth.php';
