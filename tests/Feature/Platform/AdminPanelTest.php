<?php

namespace Tests\Feature\Platform;

use App\Models\ComplaintCategory;
use App\Models\SchoolRatingComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function makeSystemAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');

        return $user;
    }

    public function test_non_admin_cannot_reach_rating_weights_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('parent');

        $this->actingAs($user)->get('/admin/rating-weights')->assertForbidden();
    }

    public function test_system_admin_can_update_school_rating_weights(): void
    {
        $admin = $this->makeSystemAdmin();
        $component = SchoolRatingComponent::create(['key' => 'safety_wellbeing', 'label' => 'Safety & Wellbeing', 'weight' => 10]);

        Volt::actingAs($admin)->test('admin.rating-weights')
            ->set("schoolWeights.{$component->id}.weight", 25.5)
            ->call('saveSchoolWeights');

        $this->assertEquals(25.5, $component->fresh()->weight);
    }

    public function test_system_admin_can_add_and_toggle_complaint_category(): void
    {
        $admin = $this->makeSystemAdmin();

        Volt::actingAs($admin)->test('admin.categories')
            ->set('newName', 'New Test Category')
            ->call('addCategory');

        $category = ComplaintCategory::where('name', 'New Test Category')->firstOrFail();
        $this->assertTrue($category->is_active);

        Volt::actingAs($admin)->test('admin.categories')
            ->call('toggleActive', $category->id);

        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_admin_actions_are_audit_logged(): void
    {
        $admin = $this->makeSystemAdmin();

        Volt::actingAs($admin)->test('admin.categories')
            ->set('newName', 'Audited Category')
            ->call('addCategory');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'complaint-category-created',
        ]);
    }
}
