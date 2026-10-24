<?php

namespace Tests\Feature\Platform;

use App\Models\Complaint;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\Feature\Platform\Concerns\SetsUpPlatformData;
use Tests\TestCase;

/**
 * complaint_number is unique. Submitting must never fail on it: not when a
 * number in the sequence is missing, and not when another submission in the
 * same district takes the next number first.
 */
class ComplaintNumberTest extends TestCase
{
    use RefreshDatabase, SetsUpPlatformData;

    private function prefix(School $school): string
    {
        return sprintf('EDU-%s-%s-%d-', strtoupper($school->state->code), strtoupper($school->district->code), now()->year);
    }

    private function existingComplaint(School $school, string $number): Complaint
    {
        return Complaint::create([
            'complaint_number' => $number,
            'school_id' => $school->id,
            'complaint_category_id' => $this->makeCategory()->id,
            'district_id' => $school->district_id,
            'state_id' => $school->state_id,
            'anonymous_ref' => 'ANON-EXISTING',
            'submitted_role' => 'parent',
            'subject' => 'Earlier complaint',
            'description' => 'An earlier complaint in the same district.',
            'status' => 'submitted',
        ]);
    }

    private function submit(School $school): void
    {
        Volt::actingAs($this->makeVerifiedParent($school))->test('complaints.create')
            ->set('schoolId', (string) $school->id)
            ->set('complaintCategoryId', (string) $this->makeCategory()->id)
            ->set('subject', 'Drinking water tap broken')
            ->set('description', 'The drinking water tap on the ground floor has been broken for a week.')
            ->set('severity', 'medium')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect();
    }

    public function test_numbering_continues_after_a_missing_number(): void
    {
        $school = $this->makeSchool();
        $prefix = $this->prefix($school);

        // Number 1 is gone (e.g. removed by hand); 2 is still there. Counting
        // the rows gives 1 + 1 = 2 again, which is taken.
        $this->existingComplaint($school, $prefix.'000002');

        $this->submit($school);

        $this->assertSame(2, Complaint::count());
        $this->assertNotNull(Complaint::where('complaint_number', $prefix.'000003')->first());
    }

    public function test_numbering_continues_past_six_digits(): void
    {
        $school = $this->makeSchool();
        $prefix = $this->prefix($school);

        // The padding is a minimum: as text, 1000000 sorts below 999999.
        $this->existingComplaint($school, $prefix.'999999');
        $this->existingComplaint($school, $prefix.'1000000');

        $this->submit($school);

        $this->assertNotNull(Complaint::where('complaint_number', $prefix.'1000001')->first());
    }

    public function test_a_number_taken_by_a_concurrent_submission_is_retried(): void
    {
        $school = $this->makeSchool();
        $prefix = $this->prefix($school);
        $this->existingComplaint($school, $prefix.'000001');

        // Stand-in for another request committing the same number between
        // this one reading the sequence and inserting: the first insert
        // attempt collides with the existing row.
        $collided = false;
        Complaint::creating(function (Complaint $complaint) use (&$collided, $prefix) {
            if (! $collided && $complaint->subject === 'Drinking water tap broken') {
                $collided = true;
                $complaint->complaint_number = $prefix.'000001';
            }
        });

        $this->submit($school);

        $this->assertTrue($collided);
        $this->assertSame(2, Complaint::count());
        $this->assertSame(
            $prefix.'000002',
            Complaint::where('subject', 'Drinking water tap broken')->value('complaint_number')
        );
    }
}
