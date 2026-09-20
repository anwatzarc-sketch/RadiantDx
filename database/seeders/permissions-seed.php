<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Permission catalogue
|--------------------------------------------------------------------------
|
| This file is the single authoritative definition of every protected
| operation in the application. PermissionSeeder mirrors it into the
| permissions table, and PermissionSeedIntegrityTest asserts the two never
| drift apart.
|
| Adding a permission here and running `php artisan db:seed` is all that is
| required: the seed is deterministic, repeatable and idempotent, and a
| super admin role always resolves to the full catalogue.
|
| Each module is rendered as one group on the role permission screen, in the
| order declared below.
|
*/

return [

    [
        'module' => 'Dashboard',
        'permissions' => [
            'dashboard.view' => 'View dashboard',
        ],
    ],

    [
        'module' => 'User Management',
        'permissions' => [
            'users.view' => 'View users',
            'users.create' => 'Create users',
            'users.update' => 'Update users',
            'users.delete' => 'Delete users',
            'users.activate' => 'Activate users',
            'users.deactivate' => 'Deactivate users',
        ],
    ],

    [
        'module' => 'Role Management',
        'permissions' => [
            'roles.view' => 'View roles',
            'roles.create' => 'Create roles',
            'roles.update' => 'Update roles',
            'roles.delete' => 'Delete roles',
            'roles.assign_permissions' => 'Assign permissions to roles',
        ],
    ],

    [
        'module' => 'Laboratory Requisitions',
        'permissions' => [
            'laboratory.requisition.view' => 'View requisitions',
            'laboratory.requisition.create' => 'Create requisitions',
            'laboratory.requisition.update' => 'Update requisitions',
            'laboratory.requisition.delete' => 'Delete requisitions',
            'laboratory.requisition.submit' => 'Submit requisitions',
            'laboratory.requisition.cancel' => 'Cancel requisitions',
        ],
    ],

    [
        'module' => 'Laboratory Results',
        'permissions' => [
            'laboratory.result.view' => 'View results',
            'laboratory.result.create' => 'Create results',
            'laboratory.result.update' => 'Enter and update results',
            'laboratory.result.delete' => 'Delete results',
            'laboratory.result.validate' => 'Validate results',
            'laboratory.result.unvalidate' => 'Unvalidate results for correction',
            'laboratory.result.print' => 'Print laboratory reports',
        ],
    ],

    [
        'module' => 'Laboratory Tests',
        'permissions' => [
            'laboratory.test.view' => 'View laboratory tests',
            'laboratory.test.create' => 'Create laboratory tests',
            'laboratory.test.update' => 'Update laboratory tests',
            'laboratory.test.delete' => 'Delete laboratory tests',
            'laboratory.test.activate' => 'Activate laboratory tests',
            'laboratory.test.deactivate' => 'Deactivate laboratory tests',
        ],
    ],

    [
        'module' => 'Laboratory Panels',
        'permissions' => [
            'laboratory.panel.view' => 'View panels',
            'laboratory.panel.create' => 'Create panels',
            'laboratory.panel.update' => 'Update panels',
            'laboratory.panel.delete' => 'Delete panels',
            'laboratory.panel.activate' => 'Activate panels',
            'laboratory.panel.deactivate' => 'Deactivate panels',
        ],
    ],

    [
        'module' => 'Test Parameters',
        'permissions' => [
            'laboratory.parameter.view' => 'View test parameters',
            'laboratory.parameter.create' => 'Create test parameters',
            'laboratory.parameter.update' => 'Update test parameters',
            'laboratory.parameter.delete' => 'Delete test parameters',
            'laboratory.parameter.activate' => 'Activate test parameters',
            'laboratory.parameter.deactivate' => 'Deactivate test parameters',
        ],
    ],

    [
        'module' => 'Staff Management',
        'permissions' => [
            'staff.view' => 'View staff records',
            'staff.create' => 'Create staff records',
            'staff.update' => 'Update staff records',
            'staff.delete' => 'Delete staff records',
            'staff.status.manage' => 'Change staff status',
            'staff.account.manage' => 'Manage staff system accounts',
            'staff.photo.manage' => 'Manage staff profile photos',
            'staff.photo.manage.self' => 'Change own profile photo',
            'staff.import' => 'Import staff records',
            'staff.export' => 'Export staff records',
            'staff.activity.view' => 'View staff activity history',
            'staff.physician.manage' => 'Manage physician licensing and practice',
            'staff.qualification.manage' => 'Manage staff qualifications',
            'profile.self.update' => 'Update own contact details',
        ],
    ],

];
