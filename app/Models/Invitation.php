<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['school_id', 'invited_by_user_id', 'email', 'role', 'student_name', 'token', 'status', 'accepted_by_user_id', 'accepted_at'])]
class Invitation extends Model
{
    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    /**
     * Stored lowercased, same as User::email(). MySQL's case-insensitive collation used
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

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public static function generateToken(): string
    {
        return Str::random(48);
    }
}
