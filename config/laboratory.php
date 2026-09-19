<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Laboratory configuration
|--------------------------------------------------------------------------
|
| Identity of the laboratory issuing the reports plus the tunables used by
| the requisition / result numbering services. Everything here is driven by
| the environment so the application can be deployed for a different site
| without touching code.
|
*/

return [

    'organisation' => [
        'name' => env('LAB_ORGANISATION_NAME', 'Harme Medical Center'),
        'department' => env('LAB_DEPARTMENT_NAME', 'Department of Laboratory Medicine'),
        'address' => env('LAB_ADDRESS_LINE', 'Harer, Ethiopia'),
        'phone' => env('LAB_CONTACT_PHONE', '+251920292354'),
        'email' => env('LAB_CONTACT_EMAIL', ''),
        'licence_number' => env('LAB_LICENCE_NUMBER', ''),

        /*
         * Mark shown on the printed report's letterhead and footer. Relative
         * paths resolve inside public/, so a site can drop in its own artwork
         * without rebuilding assets; absolute http(s) and data: URIs are also
         * accepted. Leave it empty for a text-only letterhead — the report is
         * laid out to read correctly either way.
         *
         * Prefer a compact mark over a full logo here: at letterhead size a
         * wordmark inside the artwork is too small to read.
         */
        'logo' => env('LAB_LOGO_PATH', 'images/logo-mark.svg'),
    ],

    'report' => [
        'footer' => env('LAB_REPORT_FOOTER', 'Results relate only to the specimen received.'),
    ],

    /*
     * Installable-app metadata. Driven from here for the same reason as the
     * rest of this file: another site should be able to deploy and install the
     * application under its own name and colours without touching code.
     *
     * theme_color tints the system browser chrome, so it wants to match the
     * --color-brand-900 the sidebar uses.
     */
    'pwa' => [
        'short_name' => env('LAB_PWA_SHORT_NAME', 'Harme Lab'),
        'theme_color' => env('LAB_PWA_THEME_COLOR', '#00303c'),
        'background_color' => env('LAB_PWA_BACKGROUND_COLOR', '#f8fafc'),
    ],

    'numbering' => [
        'requisition_prefix' => env('LAB_REQUISITION_PREFIX', 'REQ'),
        'result_prefix' => env('LAB_RESULT_PREFIX', 'RES'),
        'sequence_padding' => 5,
    ],

    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'System Administrator'),
        'email' => env('SUPER_ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

];
