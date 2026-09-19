<?php

declare(strict_types=1);

use App\Http\Controllers\Administration\RoleController;
use App\Http\Controllers\Administration\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Laboratory\LaboratoryPanelController;
use App\Http\Controllers\Laboratory\LaboratoryTestController;
use App\Http\Controllers\Laboratory\ReportController;
use App\Http\Controllers\Laboratory\RequisitionController;
use App\Http\Controllers\Laboratory\ResultController;
use App\Http\Controllers\Laboratory\TestParameterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PwaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Application routes
|--------------------------------------------------------------------------
|
| Every route that performs a protected operation names its permission on the
| `permission` middleware. That check is the authoritative one: navigation and
| buttons are hidden for convenience, but a direct request without the
| permission is refused with 403 regardless of what the interface showed.
|
*/

Route::redirect('/', '/admin/dashboard')->name('home');

/*
 * Installable-app endpoints. Unauthenticated by necessity: the browser reads
 * the manifest before sign-in, and the service worker caches the offline page
 * at install time. Neither exposes anything patient identifying.
 */
Route::get('manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('offline', [PwaController::class, 'offline'])->name('pwa.offline');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'active'])->group(function (): void {

    // The profile stays reachable while a temporary password is outstanding.
    Route::prefix('profile')->name('profile.')->group(function (): void {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::put('password', [ProfileController::class, 'updatePassword'])->name('password.update');
    });

    Route::prefix('admin')->middleware('password.changed')->group(function (): void {

        Route::redirect('/', '/admin/dashboard');

        Route::get('dashboard', DashboardController::class)
            ->middleware('permission:dashboard.view')
            ->name('dashboard');

        /*
        |----------------------------------------------------------------------
        | Administration
        |----------------------------------------------------------------------
        */
        Route::prefix('users')->name('administration.users.')->group(function (): void {
            Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view')->name('index');
            Route::get('create', [UserController::class, 'create'])->middleware('permission:users.create')->name('create');
            Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create')->name('store');
            Route::get('{user}', [UserController::class, 'show'])->middleware('permission:users.view')->name('show');
            Route::get('{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.update')->name('edit');
            Route::put('{user}', [UserController::class, 'update'])->middleware('permission:users.update')->name('update');
            Route::patch('{user}/activate', [UserController::class, 'activate'])->middleware('permission:users.activate')->name('activate');
            Route::patch('{user}/deactivate', [UserController::class, 'deactivate'])->middleware('permission:users.deactivate')->name('deactivate');
            Route::delete('{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('destroy');
        });

        Route::prefix('roles')->name('administration.roles.')->group(function (): void {
            Route::get('/', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('index');
            Route::get('create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('create');
            Route::post('/', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('store');
            Route::get('{role}', [RoleController::class, 'show'])->middleware('permission:roles.view')->name('show');
            Route::get('{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.update')->name('edit');
            Route::put('{role}', [RoleController::class, 'update'])->middleware('permission:roles.update')->name('update');
            Route::delete('{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('destroy');
        });

        /*
        |----------------------------------------------------------------------
        | Laboratory catalogue
        |----------------------------------------------------------------------
        */
        Route::prefix('laboratory')->name('laboratory.')->group(function (): void {

            Route::prefix('tests')->name('tests.')->group(function (): void {
                Route::get('/', [LaboratoryTestController::class, 'index'])->middleware('permission:laboratory.test.view')->name('index');
                Route::get('create', [LaboratoryTestController::class, 'create'])->middleware('permission:laboratory.test.create')->name('create');
                Route::post('/', [LaboratoryTestController::class, 'store'])->middleware('permission:laboratory.test.create')->name('store');
                Route::get('{test}', [LaboratoryTestController::class, 'show'])->middleware('permission:laboratory.test.view')->name('show');
                Route::get('{test}/edit', [LaboratoryTestController::class, 'edit'])->middleware('permission:laboratory.test.update')->name('edit');
                Route::put('{test}', [LaboratoryTestController::class, 'update'])->middleware('permission:laboratory.test.update')->name('update');
                Route::patch('{test}/activate', [LaboratoryTestController::class, 'activate'])->middleware('permission:laboratory.test.activate')->name('activate');
                Route::patch('{test}/deactivate', [LaboratoryTestController::class, 'deactivate'])->middleware('permission:laboratory.test.deactivate')->name('deactivate');
                Route::delete('{test}', [LaboratoryTestController::class, 'destroy'])->middleware('permission:laboratory.test.delete')->name('destroy');
            });

            Route::prefix('parameters')->name('parameters.')->group(function (): void {
                Route::get('/', [TestParameterController::class, 'index'])->middleware('permission:laboratory.parameter.view')->name('index');
                Route::get('create', [TestParameterController::class, 'create'])->middleware('permission:laboratory.parameter.create')->name('create');
                Route::post('/', [TestParameterController::class, 'store'])->middleware('permission:laboratory.parameter.create')->name('store');
                Route::get('{parameter}', [TestParameterController::class, 'show'])->middleware('permission:laboratory.parameter.view')->name('show');
                Route::get('{parameter}/edit', [TestParameterController::class, 'edit'])->middleware('permission:laboratory.parameter.update')->name('edit');
                Route::put('{parameter}', [TestParameterController::class, 'update'])->middleware('permission:laboratory.parameter.update')->name('update');
                Route::patch('{parameter}/activate', [TestParameterController::class, 'activate'])->middleware('permission:laboratory.parameter.activate')->name('activate');
                Route::patch('{parameter}/deactivate', [TestParameterController::class, 'deactivate'])->middleware('permission:laboratory.parameter.deactivate')->name('deactivate');
                Route::delete('{parameter}', [TestParameterController::class, 'destroy'])->middleware('permission:laboratory.parameter.delete')->name('destroy');
            });

            Route::prefix('panels')->name('panels.')->group(function (): void {
                Route::get('/', [LaboratoryPanelController::class, 'index'])->middleware('permission:laboratory.panel.view')->name('index');
                Route::get('create', [LaboratoryPanelController::class, 'create'])->middleware('permission:laboratory.panel.create')->name('create');
                Route::post('/', [LaboratoryPanelController::class, 'store'])->middleware('permission:laboratory.panel.create')->name('store');
                Route::get('{panel}', [LaboratoryPanelController::class, 'show'])->middleware('permission:laboratory.panel.view')->name('show');
                Route::get('{panel}/edit', [LaboratoryPanelController::class, 'edit'])->middleware('permission:laboratory.panel.update')->name('edit');
                Route::put('{panel}', [LaboratoryPanelController::class, 'update'])->middleware('permission:laboratory.panel.update')->name('update');
                Route::patch('{panel}/activate', [LaboratoryPanelController::class, 'activate'])->middleware('permission:laboratory.panel.activate')->name('activate');
                Route::patch('{panel}/deactivate', [LaboratoryPanelController::class, 'deactivate'])->middleware('permission:laboratory.panel.deactivate')->name('deactivate');
                Route::delete('{panel}', [LaboratoryPanelController::class, 'destroy'])->middleware('permission:laboratory.panel.delete')->name('destroy');
            });

            /*
            |------------------------------------------------------------------
            | Requisition workflow
            |------------------------------------------------------------------
            */
            Route::prefix('requisitions')->name('requisitions.')->group(function (): void {
                Route::get('/', [RequisitionController::class, 'index'])->middleware('permission:laboratory.requisition.view')->name('index');
                Route::get('create', [RequisitionController::class, 'create'])->middleware('permission:laboratory.requisition.create')->name('create');
                Route::post('/', [RequisitionController::class, 'store'])->middleware('permission:laboratory.requisition.create')->name('store');
                Route::get('{requisition}', [RequisitionController::class, 'show'])->middleware('permission:laboratory.requisition.view')->name('show');
                Route::get('{requisition}/edit', [RequisitionController::class, 'edit'])->middleware('permission:laboratory.requisition.update')->name('edit');
                Route::put('{requisition}', [RequisitionController::class, 'update'])->middleware('permission:laboratory.requisition.update')->name('update');
                Route::post('{requisition}/submit', [RequisitionController::class, 'submit'])->middleware('permission:laboratory.requisition.submit')->name('submit');
                Route::post('{requisition}/transition', [RequisitionController::class, 'transition'])->middleware('permission:laboratory.requisition.update')->name('transition');
                Route::post('{requisition}/cancel', [RequisitionController::class, 'cancel'])->middleware('permission:laboratory.requisition.cancel')->name('cancel');
                Route::delete('{requisition}', [RequisitionController::class, 'destroy'])->middleware('permission:laboratory.requisition.delete')->name('destroy');

                Route::get('{requisition}/report', [ReportController::class, 'requisition'])
                    ->middleware('permission:laboratory.result.print')
                    ->name('report');
            });

            /*
            |------------------------------------------------------------------
            | Result workflow
            |------------------------------------------------------------------
            */
            Route::prefix('results')->name('results.')->group(function (): void {
                Route::get('/', [ResultController::class, 'index'])->middleware('permission:laboratory.result.view')->name('index');
                Route::post('items/{item}', [ResultController::class, 'store'])->middleware('permission:laboratory.result.create')->name('store');
                Route::get('{result}', [ResultController::class, 'show'])->middleware('permission:laboratory.result.view')->name('show');
                Route::put('{result}', [ResultController::class, 'update'])->middleware('permission:laboratory.result.update')->name('update');
                Route::post('{result}/validate', [ResultController::class, 'validateResult'])->middleware('permission:laboratory.result.validate')->name('validate');
                Route::post('{result}/unvalidate', [ResultController::class, 'unvalidate'])->middleware('permission:laboratory.result.unvalidate')->name('unvalidate');
                Route::delete('{result}', [ResultController::class, 'destroy'])->middleware('permission:laboratory.result.delete')->name('destroy');

                Route::get('{result}/print', [ReportController::class, 'result'])
                    ->middleware('permission:laboratory.result.print')
                    ->name('print');
            });
        });
    });
});
