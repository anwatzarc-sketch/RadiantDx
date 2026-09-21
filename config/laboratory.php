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

    /*
     * The application footer, shown on every page of the administration shell.
     *
     * Everything here is configuration for the same reason the letterhead is:
     * another site deploying this application has its own support desk, its own
     * accreditations and its own policy pages, and none of that belongs in a
     * template.
     *
     * Every value is optional. An empty one is omitted from the footer rather
     * than rendered blank, so a deployment with no support line simply does not
     * show one — and a policy link is never printed pointing at a page that
     * does not exist.
     */
    'footer' => [
        'tagline' => env('LAB_FOOTER_TAGLINE', 'Integrated diagnostic and laboratory management platform providing high-precision testing workflows, automated validation, and secure patient data analytics.'),

        'support_hotline' => env('LAB_SUPPORT_HOTLINE', ''),
        'support_email' => env('LAB_SUPPORT_EMAIL', ''),

        /*
         * An accreditation is a claim the laboratory is making about itself, so
         * it is asserted by the deployment rather than assumed by the software.
         * Comma separated: "ISO 15189 Accredited, HIPAA Compliant".
         */
        'accreditations' => env('LAB_ACCREDITATIONS', ''),

        /*
         * Policy pages are almost always hosted by the institution rather than
         * served from here, so each is a full URL.
         */
        'privacy_url' => env('LAB_PRIVACY_URL', ''),
        'terms_url' => env('LAB_TERMS_URL', ''),
        'data_protection_url' => env('LAB_DATA_PROTECTION_URL', ''),
    ],

    /*
     * Shown in the footer so a support call can open with which build the
     * caller is actually looking at, rather than with a guess.
     */
    'version' => env('LAB_VERSION', '2.4.0'),

    'report' => [
        'footer' => env('LAB_REPORT_FOOTER', 'Results relate only to the specimen received.'),
    ],

    /*
     * Licensing policy.
     *
     * How much notice the laboratory wants before a practising licence lapses
     * is a local decision, so the "expiring soon" window is configuration
     * rather than a number written into LicenseStatusDeriver.
     */
    'licensing' => [
        'expiring_soon_days' => (int) env('LAB_LICENCE_WARNING_DAYS', 60),
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
