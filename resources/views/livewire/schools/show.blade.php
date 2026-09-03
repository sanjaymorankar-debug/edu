<?php

use App\Models\Fee;
use App\Models\FeeRevision;
use App\Models\ParentSchoolRelationship;
use App\Models\School;
use App\Models\StudentSchoolRelationship;
use App\Services\AnnualCostCalculator;
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

        // Fees for the most recent year the school has published. Older years
        // stay in the table and stay queryable — this page just leads with the
        // current one.
        $latestFeeYear = Fee::where('school_id', $this->school->id)->max('academic_year');

        $fees = $latestFeeYear
            ? Fee::where('school_id', $this->school->id)->where('academic_year', $latestFeeYear)->get()
            : collect();

        $calculator = app(AnnualCostCalculator::class);

        return [
            'recentComplaints' => $recentComplaints,
            'verifiedTeachers' => $verifiedTeachers,
            'isLinkable' => $isLinkable,
            'canSubmit' => $linkStatus === 'verified',
            'linkStatus' => $linkStatus,
            'feeYear' => $latestFeeYear,
            'feeSummary' => $calculator->summarise($fees),
            'feeBreakdown' => $calculator->breakdownByCategory($fees),
            'feeIncreases' => FeeRevision::where('school_id', $this->school->id)
                ->whereColumn('new_amount', '>', 'previous_amount')
                ->latest('changed_at')->limit(5)->with('fee:id,label')->get(),
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
                    <p class="text-sm mt-1 flex flex-wrap items-center gap-2">
                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium
                            {{ $school->recognition_status === 'verified' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                            {{ ucfirst(str_replace('_', ' ', $school->recognition_status)) }}
                        </span>

                        {{-- Spec section 8: this badge appears only on an actual
                             confirmation against government data. A school that has
                             merely typed in a code gets the "not yet confirmed" state,
                             never the badge. --}}
                        @if ($school->isUdiseVerified())
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                UDISE Verified &middot; {{ $school->udise_code }}
                            </span>
                        @elseif ($school->udise_code)
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600"
                                title="The school provided this code. It has not yet been confirmed against government data.">
                                UDISE {{ $school->udise_code }} — not yet confirmed
                            </span>
                        @else
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">
                                No UDISE code on record
                            </span>
                        @endif
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

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-1">
                <h3 class="font-semibold text-gray-900">What it costs</h3>
                @if ($feeYear)
                    <span class="text-xs text-gray-400">{{ $feeYear }} &middot; school-reported</span>
                @endif
            </div>

            @if ($feeSummary['has_data'])
                <p class="text-xs text-gray-500 mb-4">
                    Estimated from the {{ $feeSummary['fee_count'] }} charges this school has published — not a
                    quotation, and not independently verified. One-time joining charges are shown separately so
                    the first year isn't confused with every year after it.
                </p>

                <div class="grid sm:grid-cols-2 gap-3 mb-4">
                    <div class="bg-indigo-50 rounded p-4">
                        <div class="text-xs text-indigo-700">First year, including one-time charges</div>
                        <div class="text-2xl font-bold text-indigo-900">₹{{ number_format($feeSummary['first_year_total']) }}</div>
                        @if ($feeSummary['optional_recurring'] > 0 || $feeSummary['optional_one_time'] > 0)
                            <div class="text-xs text-indigo-700 mt-1">
                                Includes ₹{{ number_format($feeSummary['optional_recurring'] + $feeSummary['optional_one_time']) }} optional
                            </div>
                        @endif
                    </div>
                    <div class="bg-gray-50 rounded p-4">
                        <div class="text-xs text-gray-600">Every year after that</div>
                        <div class="text-2xl font-bold text-gray-900">₹{{ number_format($feeSummary['continuing_year_total']) }}</div>
                        <div class="text-xs text-gray-500 mt-1">
                            ₹{{ number_format($feeSummary['mandatory_recurring']) }} mandatory
                        </div>
                    </div>
                </div>

                <h4 class="text-sm font-medium text-gray-700 mb-2">Where it goes</h4>
                <div class="space-y-1">
                    @foreach ($feeBreakdown as $row)
                        <div class="flex items-center justify-between text-sm py-1 border-b last:border-0">
                            <span class="text-gray-700">
                                {{ $row['label'] }}
                                @unless ($row['mandatory'])
                                    <span class="text-xs text-gray-400">(optional)</span>
                                @endunless
                            </span>
                            <span class="text-gray-900">₹{{ number_format($row['annual']) }}</span>
                        </div>
                    @endforeach
                </div>

                @if ($feeIncreases->isNotEmpty())
                    <div class="mt-4 pt-4 border-t">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Recent increases</h4>
                        @foreach ($feeIncreases as $increase)
                            <div class="flex flex-wrap justify-between gap-2 text-sm py-1">
                                <span class="text-gray-700">{{ $increase->fee?->label ?? 'A charge' }}</span>
                                <span class="text-gray-600">
                                    ₹{{ number_format((float) $increase->previous_amount) }} →
                                    ₹{{ number_format((float) $increase->new_amount) }}
                                    <span class="text-xs text-gray-400">{{ $increase->changed_at->format('M Y') }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            @else
                <p class="text-sm text-gray-400">
                    This school hasn't published its fees here yet. An empty fee section is not evidence of low
                    fees — it means nothing has been reported.
                </p>
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
