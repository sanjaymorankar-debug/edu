<?php

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintEvidence;
use App\Models\ParentSchoolRelationship;
use App\Models\School;
use App\Models\StudentSchoolRelationship;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $schoolId = '';
    public string $complaintCategoryId = '';
    public string $subject = '';
    public string $description = '';
    public string $severity = 'medium';
    public $evidenceFile = null;

    public function mount(): void
    {
        $this->schoolId = (string) request()->query('school', '');
    }

    /**
     * Verifies the submitter actually has a confirmed relationship with the
     * school before letting them file — a faceless complaint still has to
     * come from someone genuinely connected to the school (spec section H).
     */
    private function verifiedRole(School $school): ?string
    {
        $user = Auth::user();

        if ($user->hasRole('parent') && ParentSchoolRelationship::where('user_id', $user->id)
            ->where('school_id', $school->id)->where('status', 'verified')->exists()) {
            return 'parent';
        }

        if ($user->hasRole('student') && StudentSchoolRelationship::where('user_id', $user->id)
            ->where('school_id', $school->id)->where('status', 'verified')->exists()) {
            return 'student';
        }

        return null;
    }

    public function submit(): void
    {
        $validated = $this->validate([
            'schoolId' => ['required', 'exists:schools,id'],
            'complaintCategoryId' => ['required', 'exists:complaint_categories,id'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'evidenceFile' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $school = School::with(['state', 'district'])->findOrFail($validated['schoolId']);
        $role = $this->verifiedRole($school);

        abort_unless($role !== null, 403, 'You must be a verified parent or student of this school to submit a complaint.');

        $category = ComplaintCategory::findOrFail($validated['complaintCategoryId']);
        $anonymousRef = Auth::user()->anonymousRefFor($school, $role);

        $complaint = Complaint::create([
            'complaint_number' => Complaint::generateComplaintNumber($school->state, $school->district),
            'school_id' => $school->id,
            'complaint_category_id' => $category->id,
            'district_id' => $school->district_id,
            'state_id' => $school->state_id,
            'anonymous_ref' => $anonymousRef,
            'submitted_role' => $role,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'severity' => $validated['severity'],
            'status' => 'submitted',
            'is_child_safety_flag' => $category->is_child_safety,
        ]);

        $complaint->statusHistory()->create([
            'from_status' => null,
            'to_status' => 'submitted',
            'note' => 'Complaint submitted by verified '.$role.'.',
        ]);

        if ($this->evidenceFile) {
            $storedPath = $this->evidenceFile->store('complaint-evidence/'.$complaint->id, 'local');

            ComplaintEvidence::create([
                'complaint_id' => $complaint->id,
                'uploaded_by' => 'submitter',
                'original_filename' => $this->evidenceFile->getClientOriginalName(),
                'stored_filename' => $storedPath,
                'mime_type' => $this->evidenceFile->getMimeType(),
                'size_bytes' => $this->evidenceFile->getSize(),
                'disk' => 'local',
            ]);
        }

        $this->redirect(route('complaints.show', $complaint), navigate: true);
    }

    public function with(): array
    {
        return [
            'schools' => School::orderBy('name')->get(['id', 'name', 'city']),
            'categories' => ComplaintCategory::where('is_active', true)->orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Report a Problem</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-500 mb-6">
                Your report goes to the school and, if needed, government officers as
                <strong>Anonymous Verified {{ ucfirst(Auth::user()->hasRole('student') ? 'Student' : 'Parent') }}</strong> —
                your name, phone and email are never shown to the school.
            </p>

            <form wire:submit="submit" class="space-y-4">
                <div>
                    <x-input-label for="schoolId" value="School" />
                    <select wire:model="schoolId" id="schoolId" class="border-gray-300 rounded-md shadow-sm mt-1 w-full">
                        <option value="">Select school</option>
                        @foreach ($schools as $school)
                            <option value="{{ $school->id }}">{{ $school->name }} ({{ $school->city }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('schoolId')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="complaintCategoryId" value="Category" />
                    <select wire:model="complaintCategoryId" id="complaintCategoryId" class="border-gray-300 rounded-md shadow-sm mt-1 w-full">
                        <option value="">Select category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('complaintCategoryId')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="subject" value="Subject" />
                    <x-text-input wire:model="subject" id="subject" class="mt-1 w-full" />
                    <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea wire:model="description" id="description" rows="5" class="border-gray-300 rounded-md shadow-sm mt-1 w-full"></textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="severity" value="Severity" />
                    <select wire:model="severity" id="severity" class="border-gray-300 rounded-md shadow-sm mt-1 w-full">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>

                <div>
                    <x-input-label for="evidenceFile" value="Evidence (optional — image or PDF, max 5MB)" />
                    <input type="file" wire:model="evidenceFile" id="evidenceFile" class="mt-1 w-full text-sm" accept=".jpg,.jpeg,.png,.pdf" />
                    <div wire:loading wire:target="evidenceFile" class="text-xs text-gray-400 mt-1">Uploading...</div>
                    <x-input-error :messages="$errors->get('evidenceFile')" class="mt-2" />
                </div>

                <x-primary-button>Submit Complaint</x-primary-button>
            </form>
        </div>
    </div>
</div>
