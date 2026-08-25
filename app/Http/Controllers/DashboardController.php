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
            $user->hasRole('school_admin') => redirect()->route('dashboard.school'),
            $user->hasRole('district_officer') => redirect()->route('dashboard.district'),
            default => redirect()->route('dashboard.placeholder'),
        };
    }
}
