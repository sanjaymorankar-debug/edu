<?php

use App\Models\Complaint;
use App\Models\RetaliationReport;
use App\Models\School;
use App\Models\State;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function with(): array
    {
        $complaints = Complaint::with(['school:id,name,state_id', 'state:id,name'])
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->latest()
            ->limit(500)
            ->get();

        $stats = [
            'total_schools' => School::count(),
            'verified_schools' => School::where('recognition_status', 'verified')->count(),
            'total_complaints' => $complaints->count(),
            'unresolved' => $complaints->whereNotIn('status', ['resolved', 'closed'])->count(),
            'escalated' => $complaints->where('status', 'escalated')->count(),
            'child_safety' => $complaints->where('is_child_safety_flag', true)->count(),
        ];

        $byState = $complaints->groupBy(fn ($c) => $c->state->name ?? 'Unknown')
            ->map(fn ($group) => [
                'total' => $group->count(),
                'unresolved' => $group->whereNotIn('status', ['resolved', 'closed'])->count(),
            ])
            ->sortByDesc(fn ($row) => $row['total']);

        $openRetaliation = RetaliationReport::whereNotIn('status', ['resolved', 'closed'])->count();

        return compact('stats', 'byState', 'openRetaliation');
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">National Admin Dashboard</h2>
    </x-slot>

    <div class="py-8 max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-2xl font-bold text-gray-900">{{ $stats['total_schools'] }}</div>
                <div class="text-xs text-gray-500">Total Schools</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-2xl font-bold text-green-600">{{ $stats['verified_schools'] }}</div>
                <div class="text-xs text-gray-500">Verified</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-2xl font-bold text-gray-900">{{ $stats['total_complaints'] }}</div>
                <div class="text-xs text-gray-500">Complaints</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-2xl font-bold text-orange-600">{{ $stats['unresolved'] }}</div>
                <div class="text-xs text-gray-500">Unresolved</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-2xl font-bold text-red-700">{{ $stats['child_safety'] }}</div>
                <div class="text-xs text-gray-500">Child-Safety</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-2xl font-bold text-red-600">{{ $openRetaliation }}</div>
                <div class="text-xs text-gray-500">Open Retaliation Reports</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-semibold text-gray-900">Complaints by State</h3>
                <a href="{{ route('admin.audit-log') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">View Audit Log</a>
            </div>
            @forelse ($byState as $stateName => $row)
                <div class="flex justify-between py-2 border-b last:border-0 text-sm">
                    <span>{{ $stateName }}</span>
                    <span class="text-gray-500">{{ $row['total'] }} total &middot; {{ $row['unresolved'] }} unresolved</span>
                </div>
            @empty
                <p class="text-sm text-gray-400">No complaints recorded yet.</p>
            @endforelse
        </div>
    </div>
</div>
