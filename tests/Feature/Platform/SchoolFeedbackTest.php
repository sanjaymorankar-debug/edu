<?php

namespace Tests\Feature\Platform;

use App\Models\SchoolFeedback;
use App\Models\SchoolQualityScore;
use App\Models\SchoolRatingComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\Feature\Platform\Concerns\SetsUpPlatformData;
use Tests\TestCase;

class SchoolFeedbackTest extends TestCase
{
    use RefreshDatabase, SetsUpPlatformData;

    public function test_verified_parent_can_rate_a_school(): void
    {
        SchoolRatingComponent::create(['key' => 'teaching_learning', 'label' => 'Teaching & Learning', 'weight' => 10]);
        $school = $this->makeSchool();
        $parent = $this->makeVerifiedParent($school);

        Volt::actingAs($parent)->test('feedback.create', ['school' => $school])
            ->assertOk()
            ->set('scores.teaching_learning', 5)
            ->call('submit');

        $this->assertSame(1, SchoolFeedback::where('school_id', $school->id)->count());
        $this->assertSame(1, SchoolQualityScore::where('school_id', $school->id)->count());
    }
}
