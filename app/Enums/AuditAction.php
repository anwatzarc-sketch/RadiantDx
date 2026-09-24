<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Workflow events worth keeping for operational traceability. This is not a
 * request log: only meaningful state changes are recorded.
 */
enum AuditAction: string
{
    case UserLoggedIn = 'user.logged_in';
    case UserLoggedOut = 'user.logged_out';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserDeleted = 'user.deleted';
    case UserActivated = 'user.activated';
    case UserDeactivated = 'user.deactivated';
    case UserPasswordChanged = 'user.password_changed';

    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDeleted = 'role.deleted';
    case RolePermissionsAssigned = 'role.permissions_assigned';

    case TestCreated = 'laboratory_test.created';
    case TestUpdated = 'laboratory_test.updated';
    case TestDeleted = 'laboratory_test.deleted';
    case TestActivated = 'laboratory_test.activated';
    case TestDeactivated = 'laboratory_test.deactivated';

    case ParameterCreated = 'laboratory_parameter.created';
    case ParameterUpdated = 'laboratory_parameter.updated';
    case ParameterDeleted = 'laboratory_parameter.deleted';
    case ParameterActivated = 'laboratory_parameter.activated';
    case ParameterDeactivated = 'laboratory_parameter.deactivated';

    case ReferenceRangeCreated = 'laboratory_reference_range.created';
    case ReferenceRangeUpdated = 'laboratory_reference_range.updated';
    case ReferenceRangeActivated = 'laboratory_reference_range.activated';
    case ReferenceRangeDeactivated = 'laboratory_reference_range.deactivated';
    case ReferenceRangeDeleted = 'laboratory_reference_range.deleted';
    case ReferenceRangeVerified = 'laboratory_reference_range.verified';

    case PanelCreated = 'laboratory_panel.created';
    case PanelUpdated = 'laboratory_panel.updated';
    case PanelDeleted = 'laboratory_panel.deleted';
    case PanelActivated = 'laboratory_panel.activated';
    case PanelDeactivated = 'laboratory_panel.deactivated';

    case RequisitionCreated = 'requisition.created';
    case RequisitionUpdated = 'requisition.updated';
    case RequisitionSubmitted = 'requisition.submitted';
    case RequisitionCollected = 'requisition.collected';
    case RequisitionProcessing = 'requisition.processing';
    case RequisitionCompleted = 'requisition.completed';
    case RequisitionCancelled = 'requisition.cancelled';
    case RequisitionDeleted = 'requisition.deleted';

    case ResultCreated = 'result.created';
    case ResultUpdated = 'result.updated';
    case ResultDeleted = 'result.deleted';
    case ResultValidated = 'result.validated';
    case ResultUnvalidated = 'result.unvalidated';
    case ResultPrinted = 'result.printed';

    case StaffCreated = 'staff.created';
    case StaffUpdated = 'staff.updated';
    case StaffStatusChanged = 'staff.status_changed';
    case StaffImported = 'staff.imported';
    case StaffExported = 'staff.exported';
    case StaffAccountCreated = 'staff.account_created';
    case StaffAccountLinked = 'staff.account_linked';
    case StaffAccountDisabled = 'staff.account_disabled';

    public function label(): string
    {
        return match ($this) {
            self::UserLoggedIn => 'Signed in',
            self::UserLoggedOut => 'Signed out',
            self::UserCreated => 'User created',
            self::UserUpdated => 'User updated',
            self::UserDeleted => 'User deleted',
            self::UserActivated => 'User activated',
            self::UserDeactivated => 'User deactivated',
            self::UserPasswordChanged => 'Password changed',
            self::RoleCreated => 'Role created',
            self::RoleUpdated => 'Role updated',
            self::RoleDeleted => 'Role deleted',
            self::RolePermissionsAssigned => 'Role permissions updated',
            self::TestCreated => 'Laboratory test created',
            self::TestUpdated => 'Laboratory test updated',
            self::TestDeleted => 'Laboratory test deleted',
            self::TestActivated => 'Laboratory test activated',
            self::TestDeactivated => 'Laboratory test deactivated',
            self::ParameterCreated => 'Parameter created',
            self::ParameterUpdated => 'Parameter updated',
            self::ParameterDeleted => 'Parameter deleted',
            self::ParameterActivated => 'Parameter activated',
            self::ParameterDeactivated => 'Parameter deactivated',
            self::PanelCreated => 'Panel created',
            self::PanelUpdated => 'Panel updated',
            self::PanelDeleted => 'Panel deleted',
            self::PanelActivated => 'Panel activated',
            self::PanelDeactivated => 'Panel deactivated',
            self::ReferenceRangeCreated => 'Reference range created',
            self::ReferenceRangeUpdated => 'Reference range updated',
            self::ReferenceRangeActivated => 'Reference range activated',
            self::ReferenceRangeDeactivated => 'Reference range deactivated',
            self::ReferenceRangeDeleted => 'Reference range deleted',
            self::ReferenceRangeVerified => 'Reference range verified',
            self::RequisitionCreated => 'Requisition created',
            self::RequisitionUpdated => 'Requisition updated',
            self::RequisitionSubmitted => 'Requisition submitted',
            self::RequisitionCollected => 'Specimen collected',
            self::RequisitionProcessing => 'Processing started',
            self::RequisitionCompleted => 'Requisition completed',
            self::RequisitionCancelled => 'Requisition cancelled',
            self::RequisitionDeleted => 'Requisition deleted',
            self::ResultCreated => 'Result created',
            self::ResultUpdated => 'Result updated',
            self::ResultDeleted => 'Result deleted',
            self::ResultValidated => 'Result validated',
            self::ResultUnvalidated => 'Result unvalidated',
            self::ResultPrinted => 'Result printed',
            self::StaffCreated => 'Staff record created',
            self::StaffUpdated => 'Staff record updated',
            self::StaffStatusChanged => 'Staff status changed',
            self::StaffImported => 'Staff imported',
            self::StaffExported => 'Staff exported',
            self::StaffAccountCreated => 'Account created for staff',
            self::StaffAccountLinked => 'Account linked to staff',
            self::StaffAccountDisabled => 'Staff account disabled',
        };
    }

    /** Accent used by the activity timeline. */
    public function toneClasses(): string
    {
        return match (true) {
            in_array($this, [self::ResultValidated, self::RequisitionCompleted, self::ReferenceRangeVerified], true) => 'bg-emerald-500',
            in_array($this, [
                self::RequisitionCancelled,
                self::ResultUnvalidated,
                self::UserDeleted,
                self::RoleDeleted,
                self::RequisitionDeleted,
                self::ResultDeleted,
                self::ReferenceRangeDeleted,
            ], true) => 'bg-rose-500',
            in_array($this, [self::ResultPrinted, self::UserLoggedIn, self::UserLoggedOut], true) => 'bg-slate-400',
            default => 'bg-sky-500',
        };
    }
}
