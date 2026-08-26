<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'school_code', 'name', 'board', 'management_type', 'state_id', 'district_id',
    'address', 'city', 'pincode', 'phone', 'email', 'website', 'recognition_status',
    'classes_from', 'classes_to', 'student_count', 'teacher_count', 'established_year',
])]
class School extends Model
{
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(SchoolProfile::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(SchoolStaff::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(SchoolFeedback::class);
    }

    public function qualityScores(): HasMany
    {
        return $this->hasMany(SchoolQualityScore::class);
    }

    public function latestQualityScore(): HasOne
    {
        return $this->hasOne(SchoolQualityScore::class)->latestOfMany('calculated_at');
    }

    public function parentRelationships(): HasMany
    {
        return $this->hasMany(ParentSchoolRelationship::class);
    }

    public function studentRelationships(): HasMany
    {
        return $this->hasMany(StudentSchoolRelationship::class);
    }

    public function teacherRelationships(): HasMany
    {
        return $this->hasMany(TeacherSchoolRelationship::class);
    }

    public function teacherFeedback(): HasMany
    {
        return $this->hasMany(TeacherFeedback::class);
    }

    public function retaliationReports(): HasMany
    {
        return $this->hasMany(RetaliationReport::class);
    }

    /**
     * Teachers verified at this school — used to populate "rate this
     * teacher" pickers on the school profile page.
     */
    public function verifiedTeachers()
    {
        return User::whereIn('id', $this->teacherRelationships()->where('status', 'verified')->pluck('user_id'));
    }
}
