<?php

use App\Models\SchoolStaff;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function with(): array
    {
        $schoolIds = SchoolStaff::where('user_id', Auth::id())->pluck('school_id');
        $school = \App\Models\School::whereIn('id', $schoolIds)->with('latestQualityScore')->first();

        $complaints = $school
            ? $school->complaints()->with('category:id,name')->latest()->limit(20)->get()
            : collect();

        $stats = [
            'open' => $complaints->whereNotIn('status', ['resolved', 'closed'])->count(),
            'overdue' => $complaints->where('status', 'submitted')->where('created_at', '<', now()->subDays(7))->count(),
            'resolved' => $complaints->whereIn('status', ['resolved', 'closed'])->count(),
        ];

        return compact('school', 'complaints', 'stats');
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">School Dashboard{{ $school ? ' — '.$school->name : '' }}</h2>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (! $school)
            <div class="bg-white rounded-lg shadow p-6 text-gray-500">No school is linked to your account yet.</div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-2xl font-bold text-indigo-600">{{ $school->latestQualityScore?->score ?? '—' }}</div>
                    <div class="text-xs text-gray-500">School Quality Index</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['open'] }}</div>
                    <div class="text-xs text-gray-500">Open Complaints</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-2xl font-bold text-red-600">{{ $stats['overdue'] }}</div>
                    <div class="text-xs text-gray-500">Overdue (&gt;7 days, no response)</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-2xl font-bold text-green-600">{{ $stats['resolved'] }}</div>
                    <div class="text-xs text-gray-500">Resolved</div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Complaints</h3>
                @forelse ($complaints as $complaint)
                    <a href="{{ route('complaints.show', $complaint) }}" wire:navigate class="flex justify-between py-2 border-b last:border-0 text-sm hover:bg-gray-50 -mx-2 px-2 rounded">
                        <span>{{ $complaint->complaint_number }} — {{ $complaint->category->name }}</span>
                        <span class="text-gray-400">{{ str_replace('_', ' ', $complaint->status) }}</span>
                    </a>
                @empty
                    <p class="text-sm text-gray-400">No complaints yet.</p>
                @endforelse
            </div>
        @endif
    </div>
</div>
