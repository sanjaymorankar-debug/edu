<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $user = Auth::user();

        return match (true) {
            $user->hasRole('parent') => redirect()->route('dashboard.parent'),
            $user->hasRole('student') => redirect()->route('dashboard.student'),
            $user->hasRole('teacher') => redirect()->route('dashboard.teacher'),
            $user->hasRole('school_admin') => redirect()->route('dashboard.school'),
            $user->hasRole('district_officer') => redirect()->route('dashboard.district'),
            $user->hasRole('state_officer') => redirect()->route('dashboard.state'),
            $user->hasRole('national_admin') => redirect()->route('dashboard.national'),
            $user->hasRole('researcher') => redirect()->route('dashboard.researcher'),
            $user->hasRole('system_admin') => redirect()->route('dashboard.admin'),
            default => redirect()->route('dashboard.placeholder'),
        };
    }
}
