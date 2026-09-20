<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * The catalogue of fields a client might use to nominate who performed an
 * action, and which the server therefore ignores.
 *
 * A class rather than a constant on the trait so the list can be read from
 * anywhere — a test asserting the contract, a future middleware — without
 * having to use the trait to get at it.
 *
 * Deliberately broader than the columns that exist today. Several of these have
 * no column behind them, because this application has no review or approval
 * stage, but they are listed so that adding one later cannot quietly open the
 * hole this is here to close.
 */
final class ClientActorFields
{
    /** @var list<string> */
    public const ALL = [
        'user_id', 'staff_id', 'actor_id', 'actor_staff_id', 'actor_name',

        'requestor_name', 'requestor_staff_id', 'requesting_clinician',
        'requested_by', 'requested_by_staff_id',

        'entered_by', 'entered_by_staff_id',
        'performed_by', 'performed_by_staff_id',
        'collected_by', 'collected_by_staff_id',
        'reviewed_by', 'reviewed_by_staff_id',
        'approved_by', 'approved_by_staff_id',
        'validated_by', 'validated_by_staff_id',
        'unvalidated_by', 'unvalidated_by_staff_id',
        'printed_by', 'printed_by_staff_id',
        'released_by', 'released_by_staff_id',
        'modified_by', 'modified_by_staff_id',
        'created_by', 'updated_by', 'cancelled_by',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return self::ALL;
    }
}
