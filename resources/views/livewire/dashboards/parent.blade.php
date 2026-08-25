<?php

use App\Models\AnonymousIdentity;
use App\Models\ParentSchoolRelationship;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function with(): array
    {
        $user = Auth::user();

        $myRefs = AnonymousIdentity::where('user_id', $user->id)->pluck('anonymous_ref');

        $myComplaints = \App\Models\Complaint::whereIn('anonymous_ref', $myRefs)
            ->with('school:id,name')->latest()->get();

        $mySchools = ParentSchoolRelationship::where('user_id', $user->id)
            ->with('school:id,name,city')->get();

        return compact('myComplaints', 'mySchools');
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Parent Dashboard</h2>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-semibold text-gray-900">My Schools</h3>
                <a href="{{ route('schools.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">Find School</a>
            </div>
            @forelse ($mySchools as $rel)
                <div class="flex justify-between py-2 border-b last:border-0 text-sm">
                    <a href="{{ route('schools.show', $rel->school_id) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $rel->school->name }}</a>
                    <span class="text-gray-400">{{ ucfirst($rel->status) }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-400">No linked schools yet. Use "Find School" to link one.</p>
            @endforelse
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="font-semibold text-gray-900 mb-4">My Complaints</h3>
            @forelse ($myComplaints as $complaint)
                <a href="{{ route('complaints.show', $complaint) }}" wire:navigate class="flex justify-between py-2 border-b last:border-0 text-sm hover:bg-gray-50 -mx-2 px-2 rounded">
                    <span>{{ $complaint->complaint_number }} — {{ $complaint->subject }}</span>
                    <span class="text-gray-400">{{ str_replace('_', ' ', $complaint->status) }}</span>
                </a>
            @empty
                <p class="text-sm text-gray-400">You haven't submitted any complaints.</p>
            @endforelse
        </div>
    </div>
</div>
