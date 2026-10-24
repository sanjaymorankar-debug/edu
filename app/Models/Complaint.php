<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\UniqueConstraintViolationException;

#[Fillable([
    'complaint_number', 'school_id', 'complaint_category_id', 'district_id', 'state_id',
    'anonymous_ref', 'submitted_role', 'subject', 'description', 'severity', 'status',
    'is_child_safety_flag', 'resolved_at',
])]
class Complaint extends Model
{
    protected function casts(): array
    {
        return [
            'is_child_safety_flag' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ComplaintCategory::class, 'complaint_category_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ComplaintEvidence::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ComplaintResponse::class)->latest('id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ComplaintStatusHistory::class)->latest('created_at');
    }

    public function resolution(): HasOne
    {
        return $this->hasOne(ComplaintResolution::class);
    }

    /**
     * EDU-{STATE}-{DISTRICT}-{YEAR}-{sequence} per spec section S. The next
     * sequence number follows the highest one in use (not the row count, which
     * repeats a number once any complaint in the sequence is gone). Ordered
     * by length first: the padding is a minimum, so 1000000 sorts below
     * 999999 as text.
     */
    public static function generateComplaintNumber(State $state, District $district): string
    {
        $prefix = static::complaintNumberPrefix($state, $district);

        $last = static::where('complaint_number', 'like', $prefix.'%')
            ->orderByRaw('length(complaint_number) desc')
            ->orderByDesc('complaint_number')
            ->value('complaint_number');
        $next = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create a complaint with the next number for its state and district.
     * Two submissions in one district at the same moment would otherwise read
     * the same next number and one would fail on the unique index.
     */
    public static function createNumbered(State $state, District $district, array $attributes): static
    {
        $connection = (new static)->getConnection();
        $prefix = static::complaintNumberPrefix($state, $district);

        for ($attempt = 1; ; $attempt++) {
            try {
                // A savepoint when already inside a transaction: on PostgreSQL
                // a failed INSERT would otherwise abort the whole transaction.
                return $connection->transaction(function () use ($connection, $prefix, $state, $district, $attributes) {
                    if ($connection->getDriverName() === 'pgsql') {
                        // One numbering at a time per district and year, until
                        // this transaction ends. Transaction-scoped, so it is
                        // safe on Neon's pooler (a session lock would not be).
                        $connection->select('select pg_advisory_xact_lock(hashtext(?))', [$prefix]);
                    }

                    return static::create(
                        ['complaint_number' => static::generateComplaintNumber($state, $district)] + $attributes
                    );
                });
            } catch (UniqueConstraintViolationException $e) {
                // Only a number taken outside this path (or on SQLite) gets
                // here; take the next one.
                if ($attempt >= 5 || ! str_contains($e->getMessage(), 'complaint_number')) {
                    throw $e;
                }
            }
        }
    }

    private static function complaintNumberPrefix(State $state, District $district): string
    {
        return sprintf('EDU-%s-%s-%d-', strtoupper($state->code), strtoupper($district->code), now()->year);
    }

    public function recordStatusChange(string $toStatus, ?int $changedByUserId, ?string $note = null): void
    {
        $from = $this->status;
        $this->status = $toStatus;
        $this->save();

        $this->statusHistory()->create([
            'from_status' => $from,
            'to_status' => $toStatus,
            'changed_by_user_id' => $changedByUserId,
            'note' => $note,
        ]);
    }
}
