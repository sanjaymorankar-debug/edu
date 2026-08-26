<?php

use App\Models\ParentSchoolRelationship;
use App\Models\School;
use App\Models\SchoolFeedback;
use App\Models\SchoolRatingComponent;
use App\Models\StudentSchoolRelationship;
use App\Services\SchoolQualityIndexService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public School $school;
    public array $scores = [];
    public string $overallComment = '';

    public function mount(School $school): void
    {
        $this->school = $school;
        foreach (SchoolRatingComponent::where('is_active', true)->get() as $dimension) {
            $this->scores[$dimension->key] = 3;
        }
    }

    private function verifiedRole(): ?string
    {
        $user = Auth::user();

        if ($user->hasRole('parent') && ParentSchoolRelationship::where('user_id', $user->id)
            ->where('school_id', $this->school->id)->where('status', 'verified')->exists()) {
            return 'parent';
        }

        if ($user->hasRole('student') && StudentSchoolRelationship::where('user_id', $user->id)
            ->where('school_id', $this->school->id)->where('status', 'verified')->exists()) {
            return 'student';
        }

        return null;
    }

    public function submit(): void
    {
        $role = $this->verifiedRole();
        abort_unless($role !== null, 403, 'You must be a verified parent or student of this school to rate it.');

        $anonymousRef = Auth::user()->anonymousRefFor($this->school, $role);

        SchoolFeedback::create([
            'school_id' => $this->school->id,
            'anonymous_ref' => $anonymousRef,
            'rater_role' => $role,
            'dimension_scores' => $this->scores,
            'overall_comment' => $this->overallComment ?: null,
            'submitted_at' => now(),
        ]);

        app(SchoolQualityIndexService::class)->recalculate($this->school);

        $this->redirect(route('schools.show', $this->school), navigate: true);
    }

    public function with(): array
    {
        return ['components' => SchoolRatingComponent::where('is_active', true)->get()];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Rate {{ $school->name }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-500 mb-6">Your rating is submitted anonymously and only counted into the school's aggregate score.</p>

            <form wire:submit="submit" class="space-y-4">
                @foreach ($components as $dimension)
                    <div>
                        <div class="flex justify-between text-sm">
                            <x-input-label :value="$dimension->label" />
                            <span>{{ $scores[$dimension->key] }} / 5</span>
                        </div>
                        <input type="range" min="1" max="5" wire:model="scores.{{ $dimension->key }}" class="w-full" />
                    </div>
                @endforeach

                <div>
                    <x-input-label for="overallComment" value="Comments (optional)" />
                    <textarea wire:model="overallComment" id="overallComment" rows="3" class="border-gray-300 rounded-md shadow-sm mt-1 w-full"></textarea>
                </div>

                <x-primary-button>Submit Rating</x-primary-button>
            </form>
        </div>
    </div>
</div>
