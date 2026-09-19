<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RequisitionStatus;
use App\Enums\ResultStatus;
use App\Enums\ValidationStatus;
use App\Models\AuditLog;
use App\Models\LaboratoryPanel;
use App\Models\LaboratoryTestParameter;
use App\Models\LaboratoryRequisition;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryTest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Route;

/**
 * Operational overview of the laboratory.
 *
 * Every card and list is a piece of work someone can act on, and each one is
 * only queried when the signed in user is allowed to see it.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $canSeeRequisitions = $user->hasPermission('laboratory.requisition.view');
        $canSeeResults = $user->hasPermission('laboratory.result.view');

        return view('dashboard', [
            'cards' => $this->cards($user, $canSeeRequisitions, $canSeeResults),

            'recentRequisitions' => $canSeeRequisitions
                ? LaboratoryRequisition::query()
                    ->latest('created_at')
                    ->limit(6)
                    ->get()
                : collect(),

            'pendingValidation' => $canSeeResults
                ? LaboratoryResult::query()
                    ->with(['requisition'])
                    ->where('status', ResultStatus::Completed->value)
                    ->where('validation_status', ValidationStatus::PendingValidation->value)
                    ->oldest('performed_at')
                    ->limit(6)
                    ->get()
                : collect(),

            'recentResults' => $canSeeResults
                ? LaboratoryResult::query()
                    ->with(['requisition'])
                    ->where('validation_status', ValidationStatus::Validated->value)
                    ->latest('validated_at')
                    ->limit(6)
                    ->get()
                : collect(),

            'recentActivity' => AuditLog::query()
                ->latest('created_at')
                ->limit(8)
                ->get(),
        ]);
    }

    /**
     * @return array<int, array{label: string, value: int, hint: string, href: string|null, tone: string, icon: string}>
     */
    private function cards(User $user, bool $canSeeRequisitions, bool $canSeeResults): array
    {
        $cards = [];

        // --- Requisition Workflow Cards ---
        if ($canSeeRequisitions) {
            $cards[] = [
                'label' => 'Awaiting collection',
                'value' => LaboratoryRequisition::query()
                    ->where('status', RequisitionStatus::Submitted->value)
                    ->count(),
                'hint' => 'Submitted, specimen not yet collected',
                'href' => route('laboratory.requisitions.index', ['status' => RequisitionStatus::Submitted->value]),
                'tone' => 'sky',
                'icon' => 'inbox',
            ];

            $cards[] = [
                'label' => 'In process',
                'value' => LaboratoryRequisition::query()
                    ->whereIn('status', [
                        RequisitionStatus::Collected->value,
                        RequisitionStatus::Processing->value,
                    ])
                    ->count(),
                'hint' => 'Collected or being processed',
                'href' => route('laboratory.requisitions.index', ['status' => RequisitionStatus::Processing->value]),
                'tone' => 'amber',
                'icon' => 'beaker',
            ];

            $cards[] = [
                'label' => 'Draft requisitions',
                'value' => LaboratoryRequisition::query()
                    ->where('status', RequisitionStatus::Draft->value)
                    ->count(),
                'hint' => 'Not yet submitted to the laboratory',
                'href' => route('laboratory.requisitions.index', ['status' => RequisitionStatus::Draft->value]),
                'tone' => 'slate',
                'icon' => 'document',
            ];
        }

        // --- Result & Validation Cards ---
        if ($canSeeResults) {
            $cards[] = [
                'label' => 'Awaiting validation',
                'value' => LaboratoryResult::query()
                    ->where('status', ResultStatus::Completed->value)
                    ->where('validation_status', ValidationStatus::PendingValidation->value)
                    ->count(),
                'hint' => 'Entered and ready for review',
                'href' => route('laboratory.results.index', [
                    'validation_status' => ValidationStatus::PendingValidation->value,
                    'status' => ResultStatus::Completed->value,
                ]),
                'tone' => 'rose',
                'icon' => 'shield',
            ];

            $cards[] = [
                'label' => 'Results to enter',
                'value' => LaboratoryResult::query()
                    ->whereIn('status', [ResultStatus::Pending->value, ResultStatus::InProgress->value])
                    ->count(),
                'hint' => 'Open on the laboratory bench',
                'href' => route('laboratory.results.index', ['status' => ResultStatus::Pending->value]),
                'tone' => 'indigo',
                'icon' => 'pencil',
            ];

            $cards[] = [
                'label' => 'Validated today',
                'value' => LaboratoryResult::query()
                    ->where('validation_status', ValidationStatus::Validated->value)
                    ->whereDate('validated_at', today())
                    ->count(),
                'hint' => 'Reports released today',
                'href' => route('laboratory.results.index', [
                    'validation_status' => ValidationStatus::Validated->value,
                ]),
                'tone' => 'emerald',
                'icon' => 'check',
            ];
        }

        // --- Catalogue Management Cards ---
        if ($user->hasPermission('laboratory.test.view')) {
            $cards[] = [
                'label' => 'Active tests',
                'value' => LaboratoryTest::query()->where('is_active', true)->count(),
                'hint' => 'Available for requisition',
                'href' => route('laboratory.tests.index', ['status' => 'active']),
                'tone' => 'emerald',
                'icon' => 'catalogue',
            ];
        }

        if ($user->hasPermission('laboratory.parameter.view') && class_exists(LaboratoryTestParameter::class)) {
            $cards[] = [
                'label' => 'Test parameters',
                'value' => LaboratoryTestParameter::query()->count(),
                'hint' => 'Configured reference ranges',
                'href' => route('laboratory.parameters.index'),
                'tone' => 'cyan',
                'icon' => 'sliders',
            ];
        }

        if ($user->hasPermission('laboratory.panel.view')) {
            $cards[] = [
                'label' => 'Active panels',
                'value' => LaboratoryPanel::query()->where('is_active', true)->count(),
                'hint' => 'Grouped investigations',
                'href' => route('laboratory.panels.index', ['status' => 'active']),
                'tone' => 'purple',
                'icon' => 'layers',
            ];
        }

      // --- System Administration Cards ---
if ($user->hasPermission('user.view') && class_exists(User::class)) {
    $cards[] = [
        'label' => 'System users',
        'value' => User::query()->count(),
        'hint' => 'Active system accounts',
        'href' => Route::has('admin.users.index') ? route('admin.users.index') : null,
        'tone' => 'blue',
        'icon' => 'users',
    ];
}

if ($user->hasPermission('role.view') && class_exists(Role::class)) {
    $cards[] = [
        'label' => 'Roles & permissions',
        'value' => Role::query()->count(),
        'hint' => 'Configured access profiles',
        'href' => Route::has('admin.roles.index') ? route('admin.roles.index') : null,
        'tone' => 'indigo',
        'icon' => 'key',
    ];
}

        return $cards;
    }
}