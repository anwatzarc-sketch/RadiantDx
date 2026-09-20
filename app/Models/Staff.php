<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\LicenseStatus;
use App\Enums\PhysicianPracticeStatus;
use App\Enums\PositionType;
use App\Enums\Profession;
use App\Enums\RegistrationStatus;
use App\Enums\Speciality;
use App\Enums\StaffStatus;
use App\Enums\SubSpeciality;
use App\Exceptions\WorkflowViolationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A member of staff: who someone is professionally, independent of whether they
 * hold a system account.
 *
 * @property int $id
 * @property string $staff_id
 * @property string $full_name
 * @property StaffStatus $status
 * @property-read User|null $user
 */
class Staff extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'staff';

    /**
     * `staff_id` is deliberately absent: it is issued by the server and must
     * never be settable from a request, an import row or a form. See the
     * booted() hook, which refuses to let it change once issued.
     */
    protected $fillable = [
        'full_name',
        'gender',
        'date_of_birth',
        'phone',
        'email',
        'address',
        'title',
        'profession',
        'speciality',
        'sub_speciality',
        'department_id',
        'unit_id',
        'position',
        'professional_license',
        'license_authority',
        'license_issued_on',
        'license_expiry',
        'license_status',
        'registration_number',
        'registration_authority',
        'registration_status',
        'practice_status',
        'professional_phone',
        'professional_email',
        'professional_bio',
        'employee_id',
        'employment_type',
        'status',
        'joined_on',
        'supervisor_id',
        'needs_review',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'profession' => Profession::class,
            'speciality' => Speciality::class,
            'sub_speciality' => SubSpeciality::class,
            'position' => PositionType::class,
            'employment_type' => EmploymentType::class,
            'license_status' => LicenseStatus::class,
            'registration_status' => RegistrationStatus::class,
            'practice_status' => PhysicianPracticeStatus::class,
            'license_issued_on' => 'date',
            'status' => StaffStatus::class,
            'date_of_birth' => 'date',
            'license_expiry' => 'date',
            'joined_on' => 'date',
            'needs_review' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        /*
         * A staff identifier is printed on reports and referenced by historical
         * records. Once issued it is the permanent handle for that person, so
         * changing it is refused here rather than merely discouraged — this
         * catches a stray fill(), an import, and a console session alike.
         */
        static::updating(function (self $staff): void {
            if ($staff->isDirty('staff_id')) {
                throw WorkflowViolationException::because(
                    'A staff identifier cannot be changed once it has been issued.'
                );
            }
        });
    }

    /** @return HasOne<User, $this> */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'staff_id');
    }

    /** @return HasMany<StaffQualification, $this> */
    public function qualifications(): HasMany
    {
        return $this->hasMany(StaffQualification::class)->orderByDesc('awarded_year');
    }

    /**
     * Whether this person is set up to practise under a licence.
     *
     * Used to decide whether the physician sections are worth showing at all,
     * rather than presenting licensing fields to a phlebotomist.
     */
    public function isPhysicianProfileRelevant(): bool
    {
        return $this->profession?->requiresLicence() ?? false;
    }

    /**
     * How complete the physician profile is, as a percentage.
     *
     * Directories filter on this, and a half-filled profile on a report is
     * worth surfacing before somebody notices it in print.
     */
    public function profileCompletion(): int
    {
        $expected = [
            $this->title, $this->profession, $this->speciality,
            $this->professional_license, $this->license_expiry,
            $this->department_id, $this->position, $this->practice_status,
        ];

        $present = count(array_filter($expected, static fn ($value): bool => $value !== null && $value !== ''));

        return (int) round(($present / count($expected)) * 100);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Staff, $this> */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    /** @return HasMany<Staff, $this> */
    public function supervisees(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id');
    }

    /** Whether this person may sign in and perform laboratory work. */
    public function permitsSystemAccess(): bool
    {
        return $this->status->permitsSystemAccess();
    }

    /**
     * Whether removing this record would orphan historical activity.
     *
     * A staff member who has ever acted on a laboratory record must be retired
     * rather than deleted, so reports issued in their name keep resolving.
     */
    public function hasLaboratoryHistory(): bool
    {
        if ($this->user === null) {
            return false;
        }

        $userId = $this->user->getKey();

        return LaboratoryResult::query()
            ->where(fn (Builder $query) => $query
                ->where('performed_by', $userId)
                ->orWhere('validated_by', $userId)
                ->orWhere('created_by', $userId))
            ->exists()
            || LaboratoryRequisition::query()
                ->where(fn (Builder $query) => $query
                    ->where('created_by', $userId)
                    ->orWhere('cancelled_by', $userId))
                ->exists();
    }

    /** Display form: "Dr Amina Hassan" when a title is recorded. */
    public function displayName(): string
    {
        $title = trim((string) $this->title);

        return $title === '' ? $this->full_name : $title.' '.$this->full_name;
    }

    /** The professional line shown beneath a name on reports and directories. */
    public function professionalSummary(): ?string
    {
        $parts = array_filter([
            $this->speciality?->label(),
            $this->profession?->label(),
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** @param Builder<$this> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', StaffStatus::Active->value);
    }
}
