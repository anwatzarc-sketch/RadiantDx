<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\LaboratoryPanel;
use App\Models\LaboratoryRequisition;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestParameter;
use App\Models\Role;
use App\Models\User;
use App\Policies\LaboratoryPanelPolicy;
use App\Policies\LaboratoryRequisitionPolicy;
use App\Policies\LaboratoryResultPolicy;
use App\Policies\LaboratoryTestParameterPolicy;
use App\Policies\LaboratoryTestPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Support\PermissionCatalogue;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Authorisation is layered.
 *
 *  - A gate named after each catalogue permission answers "may this user
 *    perform this kind of operation at all". Navigation and action visibility
 *    are driven from these.
 *  - A policy answers "is this operation legal against this record right now",
 *    combining the permission with the state of the workflow.
 *
 * There is deliberately no Gate::before shortcut for administrators: a super
 * admin holds every permission, but record level rules such as "a validated
 * result is finalised" still apply to them.
 */
class AuthorizationServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private const POLICIES = [
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        LaboratoryTest::class => LaboratoryTestPolicy::class,
        LaboratoryTestParameter::class => LaboratoryTestParameterPolicy::class,
        LaboratoryPanel::class => LaboratoryPanelPolicy::class,
        LaboratoryRequisition::class => LaboratoryRequisitionPolicy::class,
        LaboratoryResult::class => LaboratoryResultPolicy::class,
    ];

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        foreach (PermissionCatalogue::names() as $permission) {
            Gate::define($permission, static fn (User $user): bool => $user->hasPermission($permission));
        }
    }
}
