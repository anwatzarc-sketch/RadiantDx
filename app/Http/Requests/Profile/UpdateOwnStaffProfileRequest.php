<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Rules\SharedEnumValue;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The part of your own staff record you may change yourself.
 *
 * The allow-list below is the security boundary, and it is deliberately an
 * allow-list rather than a set of fields omitted from a form. Omitting a field
 * from the markup stops it being shown; it does not stop it being posted.
 *
 * Everything administrative — speciality, profession, department, position,
 * employment, status, licence standing, employee ID and the staff identifier
 * itself — is absent here, so a crafted request cannot promote its sender.
 */
class UpdateOwnStaffProfileRequest extends FormRequest
{
    /**
     * The only staff attributes a person may set on their own record.
     *
     * Contact details and a personal biography: things somebody is the
     * authority on about themselves.
     */
    public const SELF_EDITABLE = [
        'phone',
        'address',
        'professional_phone',
        'professional_email',
        'professional_bio',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'professional_phone' => ['nullable', 'string', 'max:32'],
            'professional_email' => ['nullable', 'email', 'max:255'],
            'professional_bio' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Strictly the allow-listed attributes.
     *
     * safe() already limits the result to validated keys; intersecting with the
     * constant makes the boundary explicit at the point of use, so adding a
     * rule above cannot widen what a person may change without someone also
     * editing the list.
     *
     * @return array<string, mixed>
     */
    public function selfEditableAttributes(): array
    {
        return array_intersect_key(
            $this->safe()->all(),
            array_flip(self::SELF_EDITABLE),
        );
    }
}
