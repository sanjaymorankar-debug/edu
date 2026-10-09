<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Emails are stored lowercased. MySQL's case-insensitive collation used
     * to hide case differences; PostgreSQL compares text case-sensitively,
     * so normalising on write keeps unique checks and login lookups
     * case-insensitive on every driver.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : Str::lower(trim($value)),
        );
    }

    public function parentProfile(): HasOne
    {
        return $this->hasOne(ParentProfile::class);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function schoolStaffAssignments(): HasMany
    {
        return $this->hasMany(SchoolStaff::class);
    }

    public function officerJurisdictions(): HasMany
    {
        return $this->hasMany(OfficerJurisdiction::class);
    }

    public function anonymousIdentities(): HasMany
    {
        return $this->hasMany(AnonymousIdentity::class);
    }

    public function receivedTeacherFeedback(): HasMany
    {
        return $this->hasMany(TeacherFeedback::class, 'teacher_user_id');
    }

    public function effectivenessScores(): HasMany
    {
        return $this->hasMany(TeacherEffectivenessScore::class, 'teacher_user_id');
    }

    /**
     * Stable per-school pseudonym for this user, created on first use.
     * This is the only bridge between a real identity and the anonymized
     * complaint/feedback tables — see AnonymousIdentity.
     */
    public function anonymousRefFor(School $school, string $context): string
    {
        return AnonymousIdentity::firstOrCreate(
            ['user_id' => $this->id, 'school_id' => $school->id, 'context' => $context],
            ['anonymous_ref' => AnonymousIdentity::generateRef()]
        )->anonymous_ref;
    }
}
