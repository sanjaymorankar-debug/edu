<?php

use App\Models\ParentSchoolRelationship;
use App\Models\SchoolStaff;
use App\Models\StudentSchoolRelationship;
use App\Models\TeacherSchoolRelationship;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function approveParent(int $id): void
    {
        $this->authorizeRelationship(ParentSchoolRelationship::findOrFail($id))->update(['status' => 'verified', 'verified_at' => now()]);
    }

    public function rejectParent(int $id): void
    {
        $this->authorizeRelationship(ParentSchoolRelationship::findOrFail($id))->update(['status' => 'rejected']);
    }

    public function approveStudent(int $id): void
    {
        $this->authorizeRelationship(StudentSchoolRelationship::findOrFail($id))->update(['status' => 'verified', 'verified_at' => now()]);
    }

    public function rejectStudent(int $id): void
    {
        $this->authorizeRelationship(StudentSchoolRelationship::findOrFail($id))->update(['status' => 'rejected']);
    }

    public function approveTeacher(int $id): void
    {
        $this->authorizeRelationship(TeacherSchoolRelationship::findOrFail($id))->update(['status' => 'verified', 'verified_at' => now()]);
    }

    public function rejectTeacher(int $id): void
    {
        $this->authorizeRelationship(TeacherSchoolRelationship::findOrFail($id))->update(['status' => 'rejected']);
    }

    /**
     * Every approve/reject action re-checks the relationship's school_id
     * against this admin's own school_staff assignment — a school admin
     * must never be able to approve a link for a different school.
     */
    private function authorizeRelationship(ParentSchoolRelationship|StudentSchoolRelationship|TeacherSchoolRelationship $relationship): ParentSchoolRelationship|StudentSchoolRelationship|TeacherSchoolRelationship
    {
        $mySchoolIds = SchoolStaff::where('user_id', Auth::id())->pluck('school_id');
        abort_unless($mySchoolIds->contains($relationship->school_id), 403);

        return $relationship;
    }

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

        $pendingParents = $school ? ParentSchoolRelationship::where('school_id', $school->id)->where('status', 'pending')->with('user:id,name')->get() : collect();
        $pendingStudents = $school ? StudentSchoolRelationship::where('school_id', $school->id)->where('status', 'pending')->with('user:id,name')->get() : collect();
        $pendingTeachers = $school ? TeacherSchoolRelationship::where('school_id', $school->id)->where('status', 'pending')->with('user:id,name')->get() : collect();

        return compact('school', 'complaints', 'stats', 'pendingParents', 'pendingStudents', 'pendingTeachers');
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
            @if ($school->recognition_status !== 'verified')
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-800">
                    This school is <strong>{{ str_replace('_', ' ', $school->recognition_status) }}</strong> — it won't
                    appear in public search or ratings until a District/State Officer verifies it. You can still
                    manage its profile and respond to complaints in the meantime.
                </div>
            @endif

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

            @php $pendingTotal = $pendingParents->count() + $pendingStudents->count() + $pendingTeachers->count(); @endphp
            @if ($pendingTotal > 0)
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Pending Verifications ({{ $pendingTotal }})</h3>

                    @foreach ($pendingParents as $rel)
                        <div class="flex justify-between items-center py-2 border-b text-sm">
                            <span>{{ $rel->user->name }} — Parent</span>
                            <span class="flex gap-2">
                                <button wire:click="approveParent({{ $rel->id }})" class="text-xs px-2 py-1 bg-green-600 text-white rounded">Approve</button>
                                <button wire:click="rejectParent({{ $rel->id }})" class="text-xs px-2 py-1 bg-gray-200 text-gray-700 rounded">Reject</button>
                            </span>
                        </div>
                    @endforeach

                    @foreach ($pendingStudents as $rel)
                        <div class="flex justify-between items-center py-2 border-b text-sm">
                            <span>{{ $rel->user->name }} — Student (Class {{ $rel->class_grade }})</span>
                            <span class="flex gap-2">
                                <button wire:click="approveStudent({{ $rel->id }})" class="text-xs px-2 py-1 bg-green-600 text-white rounded">Approve</button>
                                <button wire:click="rejectStudent({{ $rel->id }})" class="text-xs px-2 py-1 bg-gray-200 text-gray-700 rounded">Reject</button>
                            </span>
                        </div>
                    @endforeach

                    @foreach ($pendingTeachers as $rel)
                        <div class="flex justify-between items-center py-2 border-b text-sm">
                            <span>{{ $rel->user->name }} — Teacher</span>
                            <span class="flex gap-2">
                                <button wire:click="approveTeacher({{ $rel->id }})" class="text-xs px-2 py-1 bg-green-600 text-white rounded">Approve</button>
                                <button wire:click="rejectTeacher({{ $rel->id }})" class="text-xs px-2 py-1 bg-gray-200 text-gray-700 rounded">Reject</button>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

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
