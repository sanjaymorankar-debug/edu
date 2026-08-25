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
    Volt::route('dashboard/school', 'dashboards.school')->name('dashboard.school')->middleware('role:school_admin');
    Volt::route('dashboard/district', 'dashboards.district')->name('dashboard.district')->middleware('role:district_officer');
    Volt::route('dashboard/placeholder', 'dashboards.placeholder')->name('dashboard.placeholder');

    Volt::route('onboarding', 'onboarding.index')->name('onboarding')->middleware('role:parent|student|teacher');

    Volt::route('complaints/create', 'complaints.create')->name('complaints.create')->middleware('role:parent|student');
    Volt::route('complaints/{complaint}', 'complaints.show')->name('complaints.show');
    Volt::route('feedback/create/{school}', 'feedback.create')->name('feedback.create')->middleware('role:parent|student');

    Route::get('complaints/{complaint}/evidence/{evidence}', ComplaintEvidenceController::class)
        ->name('complaints.evidence.download');

    Route::view('profile', 'profile')->name('profile');
});

require __DIR__.'/auth.php';
