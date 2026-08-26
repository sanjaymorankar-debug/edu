<?php

use App\Models\ParentSchoolRelationship;
use App\Models\School;
use App\Models\StudentSchoolRelationship;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public School $school;

    public function mount(School $school): void
    {
        $this->school = $school->load(['profile', 'state', 'district', 'latestQualityScore']);
    }

    public function with(): array
    {
        $recentComplaints = $this->school->complaints()
            ->select('id', 'complaint_category_id', 'status', 'created_at')
            ->with('category:id,name')
            ->latest()
            ->limit(5)
            ->get();

        $verifiedTeachers = $this->school->verifiedTeachers()->get(['id', 'name']);

        $user = Auth::user();
        $isLinkable = $user && $user->hasAnyRole(['parent', 'student']);
        $linkStatus = null;

        if ($isLinkable) {
            $relation = $user->hasRole('parent')
                ? ParentSchoolRelationship::where('user_id', $user->id)->where('school_id', $this->school->id)->first()
                : StudentSchoolRelationship::where('user_id', $user->id)->where('school_id', $this->school->id)->first();

            $linkStatus = $relation?->status;
        }

        return [
            'recentComplaints' => $recentComplaints,
            'verifiedTeachers' => $verifiedTeachers,
            'isLinkable' => $isLinkable,
            'canSubmit' => $linkStatus === 'verified',
            'linkStatus' => $linkStatus,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $school->name }}</h2>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500">{{ $school->address }}, {{ $school->city }}, {{ $school->district->name }}, {{ $school->state->name }} - {{ $school->pincode }}</p>
                    <p class="text-sm text-gray-500 mt-1">Board: {{ $school->board }} &middot; Management: {{ ucfirst($school->management_type) }} &middot; Classes: {{ $school->classes_from }} - {{ $school->classes_to }}</p>
                    <p class="text-sm mt-1">
                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium
                            {{ $school->recognition_status === 'verified' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                            {{ ucfirst(str_replace('_', ' ', $school->recognition_status)) }}
                        </span>
                    </p>
                </div>
                <div class="text-right">
                    @if ($school->latestQualityScore)
                        <div class="text-3xl font-bold text-indigo-600">{{ $school->latestQualityScore->score }}</div>
                        <div class="text-xs text-gray-400">School Quality Index &middot; {{ str_replace('_', ' ', $school->latestQualityScore->confidence) }} confidence</div>
                    @else
                        <div class="text-sm text-gray-400">Insufficient data for SQI</div>
                    @endif
                </div>
            </div>

            @if ($canSubmit)
                <div class="mt-4 flex gap-3">
                    <a href="{{ route('complaints.create', ['school' => $school->id]) }}" wire:navigate
                        class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm rounded-md hover:bg-red-700">Report a Problem</a>
                    <a href="{{ route('feedback.create', $school) }}" wire:navigate
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">Rate this School</a>
                </div>
            @elseif ($isLinkable && $linkStatus === 'pending')
                <p class="mt-4 text-sm text-yellow-700">Your link to this school is awaiting approval from the school admin.</p>
            @elseif ($isLinkable && $linkStatus === null)
                <a href="{{ route('onboarding') }}?school={{ $school->id }}" wire:navigate
                    class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">Link this school to my account</a>
            @endif
        </div>

        @if ($school->profile)
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-900 mb-2">About</h3>
                <p class="text-gray-600 text-sm">{{ $school->profile->about }}</p>

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div>
                        <h4 class="text-sm font-medium text-gray-700">Facilities</h4>
                        <p class="text-sm text-gray-500">{{ implode(', ', $school->profile->facilities ?? []) }}</p>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-gray-700">Sports</h4>
                        <p class="text-sm text-gray-500">{{ implode(', ', $school->profile->sports ?? []) }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if ($canSubmit && $verifiedTeachers->isNotEmpty())
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-900 mb-2">Rate a Teacher</h3>
                <p class="text-xs text-gray-400 mb-3">Private and anonymous — feeds only that teacher's own effectiveness index.</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($verifiedTeachers as $teacher)
                        <a href="{{ route('teacher-feedback.create', $teacher) }}" wire:navigate
                            class="text-sm px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-md text-gray-700">{{ $teacher->name }}</a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="font-semibold text-gray-900 mb-2">Recent Complaint Trends</h3>
            <p class="text-xs text-gray-400 mb-3">Category and status only — submitter identity is never shown here.</p>
            @forelse ($recentComplaints as $c)
                <div class="flex justify-between py-2 border-b last:border-0 text-sm">
                    <span>{{ $c->category->name }}</span>
                    <span class="text-gray-500">{{ str_replace('_', ' ', $c->status) }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-400">No complaints recorded.</p>
            @endforelse
        </div>
    </div>
</div>
