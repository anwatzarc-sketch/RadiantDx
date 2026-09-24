<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Seeding behaviour
|--------------------------------------------------------------------------
*/

return [

    /*
     * Whether the textbook reference ranges in the HL7 master data start out
     * usable.
     *
     * The 109 age- and sex-specific ranges are always seeded, marked as
     * placeholders, so the laboratory director has something to review and
     * verify. This decides only whether they start active:
     *
     *   true   active placeholders, and the parameters' adult default ranges
     *          filled in. Right for a local or demo database.
     *   false  inactive placeholders, and no adult defaults. Nothing is
     *          auto-flagged until the director verifies a range or fills in a
     *          default. Right for production, where an unapproved textbook
     *          range must not be put in front of a clinician.
     *
     * Defaults to true everywhere except APP_ENV=production.
     */
    'placeholder_ranges' => (bool) env('SEED_PLACEHOLDER_RANGES', env('APP_ENV', 'production') !== 'production'),

];
