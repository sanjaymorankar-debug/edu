<?php

use App\Models\Complaint;
use App\Models\School;
use App\Models\SchoolQualityScore;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Researcher role gets aggregate-only analytics — no individual complaint
 * browsing, no identity access. Deliberately narrower than officer
 * dashboards (spec section J: researcher is read-only, national scope).
 */
new #[Layout('layouts.app')] class extends Component
{
    public function with(): array
    {
        $schoolsByBoard = School::selectRaw('board, count(*) as total')->groupBy('board')->pluck('total', 'board');
        $schoolsByManagement = School::selectRaw('management_type, count(*) as total')->groupBy('management_type')->pluck('total', 'management_type');

        $complaintsByCategory = Complaint::join('complaint_categories', 'complaints.complaint_category_id', '=', 'complaint_categories.id')
            ->selectRaw('complaint_categories.name, count(*) as total')
            ->groupBy('complaint_categories.name')
            ->orderByDesc('total')
            ->pluck('total', 'name');

        $resolutionStats = [
            'total' => Complaint::count(),
            'resolved' => Complaint::whereIn('status', ['resolved', 'closed'])->count(),
            'escalated' => Complaint::where('status', 'escalated')->count(),
        ];

        $avgSqiByState = SchoolQualityScore::join('schools', 'school_quality_scores.school_id', '=', 'schools.id')
            ->join('states', 'schools.state_id', '=', 'states.id')
            ->selectRaw('states.name, avg(school_quality_scores.score) as avg_score, count(distinct schools.id) as school_count')
            ->groupBy('states.name')
            ->get();

        return compact('schoolsByBoard', 'schoolsByManagement', 'complaintsByCategory', 'resolutionStats', 'avgSqiByState');
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Researcher Dashboard</h2>
    </x-slot>

    <div class="py-8 max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-4 text-sm text-indigo-900">
            Aggregate, anonymized data only — no individual complaint detail or identity access is available to this role.
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Schools by Board</h3>
                @foreach ($schoolsByBoard as $board => $count)
                    <div class="flex justify-between py-1 text-sm"><span>{{ $board }}</span><span class="text-gray-500">{{ $count }}</span></div>
                @endforeach
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Schools by Management Type</h3>
                @foreach ($schoolsByManagement as $type => $count)
                    <div class="flex justify-between py-1 text-sm"><span>{{ ucfirst($type) }}</span><span class="text-gray-500">{{ $count }}</span></div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="font-semibold text-gray-900 mb-3">Complaint Volume by Category</h3>
            @foreach ($complaintsByCategory as $name => $count)
                <div class="flex justify-between py-1 text-sm"><span>{{ $name }}</span><span class="text-gray-500">{{ $count }}</span></div>
            @endforeach
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="font-semibold text-gray-900 mb-3">Resolution Overview</h3>
            <div class="grid grid-cols-3 gap-4 text-center">
                <div><div class="text-2xl font-bold text-gray-900">{{ $resolutionStats['total'] }}</div><div class="text-xs text-gray-500">Total Complaints</div></div>
                <div><div class="text-2xl font-bold text-green-600">{{ $resolutionStats['resolved'] }}</div><div class="text-xs text-gray-500">Resolved</div></div>
                <div><div class="text-2xl font-bold text-red-600">{{ $resolutionStats['escalated'] }}</div><div class="text-xs text-gray-500">Escalated</div></div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="font-semibold text-gray-900 mb-3">Average School Quality Index by State</h3>
            @forelse ($avgSqiByState as $row)
                <div class="flex justify-between py-1 text-sm">
                    <span>{{ $row->name }} ({{ $row->school_count }} schools)</span>
                    <span class="text-gray-500">{{ round($row->avg_score, 1) }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-400">No SQI data calculated yet.</p>
            @endforelse
        </div>
    </div>
</div>
