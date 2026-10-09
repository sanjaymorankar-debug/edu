<?php

namespace Tests\Feature\Platform;

use App\Models\District;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\Feature\Platform\Concerns\SetsUpPlatformData;
use Tests\TestCase;

/**
 * MySQL's case-insensitive collation used to make text comparisons ignore
 * case for free. PostgreSQL compares text case-sensitively, so these guard
 * the places that relied on that: school search (ILIKE via whereLike()) and
 * email lookups (emails stored lowercased, input lowercased before lookup).
 * CI runs this suite against PostgreSQL as well as SQLite.
 */
class CaseInsensitiveLookupTest extends TestCase
{
    use RefreshDatabase, SetsUpPlatformData;

    public function test_school_search_ignores_case_for_name_and_city(): void
    {
        $school = $this->makeSchool(); // "Test Public School …", city "Pune"

        Volt::test('schools.index')
            ->set('search', 'test public')
            ->assertSee($school->name);

        Volt::test('schools.index')
            ->set('search', 'PUNE')
            ->assertSee($school->name);
    }

    public function test_onboarding_school_search_ignores_case(): void
    {
        $school = $this->makeSchool();
        $parent = User::factory()->create();
        $parent->assignRole('parent');

        Volt::actingAs($parent)
            ->test('onboarding.index')
            ->set('search', 'TEST PUBLIC')
            ->assertSee($school->name);
    }

    public function test_emails_are_stored_lowercased(): void
    {
        $user = User::factory()->create(['email' => '  Mixed.Case@Example.COM ']);

        $this->assertSame('mixed.case@example.com', $user->fresh()->email);
    }

    public function test_login_ignores_email_case(): void
    {
        $user = User::factory()->create(['email' => 'someone@example.com']);

        Volt::test('pages.auth.login')
            ->set('form.email', 'SomeOne@Example.com')
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_school_registration_rejects_an_existing_email_in_different_case(): void
    {
        User::factory()->create(['email' => 'principal@example.com']);
        $state = State::create(['name' => 'Case State', 'code' => 'CS1']);
        $district = District::create(['state_id' => $state->id, 'name' => 'Case District', 'code' => 'CD1']);

        Volt::test('schools.register')
            ->set('adminName', 'Duplicate Principal')
            ->set('adminEmail', 'Principal@Example.com')
            ->set('adminPassword', 'Password123!')
            ->set('adminPassword_confirmation', 'Password123!')
            ->set('name', 'Case Test School')
            ->set('board', 'CBSE')
            ->set('managementType', 'private')
            ->set('stateId', (string) $state->id)
            ->set('districtId', (string) $district->id)
            ->set('address', '1 Case Street')
            ->set('city', 'Testville')
            ->set('pincode', '123456')
            ->call('register')
            ->assertHasErrors(['adminEmail' => 'unique']);

        $this->assertSame(1, User::where('email', 'principal@example.com')->count());
    }
}
