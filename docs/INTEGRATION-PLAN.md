# Integration Plan — Staff Identity, Physician Profile & Shared Enums

**Phase 0 (Reconnaissance) deliverable. No production code has been written.**

Prepared for the engineers implementing Phases 1–5 against this repository.

---

## 1. Platform inventory

| Aspect | Finding |
|---|---|
| Laravel | **13.32.0** (`composer.json` requires `^13.17`) |
| PHP | **8.3.29** runtime; `composer.json` requires `^8.3` |
| Livewire | **NOT PRESENT — confirmed.** No match in `composer.json`, `package.json`, `app/`, `resources/`, `config/` |
| Blade architecture | Anonymous Blade components in `resources/views/components/`; layout used as `<x-layouts.admin>`; partials in `resources/views/layouts/partials/` |
| CSS framework | **Tailwind CSS 4.3.3**, CSS-first configuration via `@theme` in `resources/css/app.css`. No Bootstrap. |
| JavaScript | **Alpine.js 3.14.9** only, registered via `Alpine.data()` in `resources/js/app.js`. No SPA framework. |
| Asset bundler | Vite 8 + `laravel-vite-plugin` 3.1, `@tailwindcss/vite` |
| Authentication | Laravel session auth on `Illuminate\Foundation\Auth\User`; bespoke `LoginController` + `LoginRequest`; `users.is_active`, `must_change_password`, `last_login_at`, `last_login_ip` |
| Authorization | **Bespoke RBAC.** `roles` + `permissions` + `role_permissions` tables, `User::hasPermission()`, 7 Laravel Policies, custom `permission` middleware. **No spatie/laravel-permission.** |
| Validation | Form Request classes under `app/Http/Requests/{Administration,Auth,Laboratory,Profile}` |
| Service architecture | Eloquent models + plain service classes in `app/Services/`. **No repositories, no action classes.** |
| Audit | Bespoke: `audit_logs` table, `AuditLog` model, `AuditLogger` service, `AuditAction` enum |
| File storage | `local` (private) default, `public` and `s3` configured. **`storage:link` NOT run; `public/storage` absent.** |
| Enum architecture | Native PHP **string-backed enums** in `app/Enums/` exposing `label()` and static `options(): array<string,string>`; consumed by `Rule::enum()` in Form Requests |
| Testing | PHPUnit 12.5.12, `tests/Feature` + `tests/Unit`. **Only the two stock `ExampleTest` files exist.** |
| Dependencies | Minimal: `laravel/framework`, `laravel/tinker`, `alpinejs`. Nothing else in production require. |

### Files inspected

`composer.json`, `package.json`, `config/filesystems.php`, `routes/web.php`,
`app/Models/{User,Role,Permission,AuditLog,LaboratoryRequisition,LaboratoryResult}.php`,
`app/Enums/*.php` (all 10), `app/Policies/*.php` (all 7),
`app/Services/AuditLogger.php`, `app/Services/Laboratory/{RequisitionService,ResultService,ReferenceNumberGenerator,BuildLaboratoryReport}.php`,
`app/Services/Administration/{UserService,RoleService}.php`,
`app/Support/PermissionCatalogue.php`,
`app/Http/Requests/Laboratory/{RequisitionRequest,ResultEntryRequest,ReasonRequest,TransitionRequisitionRequest}.php`,
`app/Http/Controllers/Laboratory/*.php`, `app/Http/Controllers/DashboardController.php`,
`database/migrations/*` (17 files), `resources/views/components/layouts/admin.blade.php`,
`resources/views/layouts/partials/sidebar.blade.php`, `resources/css/app.css`, `resources/js/app.js`.

---

## 2. Identity inventory

### `users` table

```
id, name, email, email_verified_at, password, role_id, is_active,
must_change_password, last_login_at, last_login_ip, remember_token,
created_at, updated_at, deleted_at
```

- `users.name` **exists and is the only identity in the system today.**
- **There is no `staff_id` column.**
- `$fillable`: `name, email, password, role_id, is_active, must_change_password` — no actor columns.
- Uses `SoftDeletes`.

### Existing Staff / Employee / Personnel / Physician / Doctor / MedicalStaff

**NONE.** Searched the full model namespace. `app/Models/` contains only:

```
AuditLog, LaboratoryPanel, LaboratoryRequisition, LaboratoryRequisitionItem,
LaboratoryResult, LaboratoryResultParameter, LaboratoryTest,
LaboratoryTestParameter, LaboratoryTestParameterOption, Permission, Role, User
```

**Consequence:** Phase 2 is **greenfield**, not an extension. There is no existing
entity to reconcile against and no competing Staff semantics. This materially
reduces Phase 2 risk.

### `users.name` dependencies

| Location | Use |
|---|---|
| `app/Services/AuditLogger.php:38` | `'user_name' => $actor?->name` — **the existing actor-name snapshot** |
| `app/Http/Controllers/Administration/UserController.php` (5 sites) | Flash messages only |
| `resources/views/components/layouts/admin.blade.php` | Account menu display |
| `resources/views/laboratory/reports/report.blade.php` | `$results->first()?->validatedBy?->name` — **report signatory line** |

---

## 3. Manual actor inventory  *(the critical section)*

### Headline finding

**The actor-spoofing exposure this specification is built around largely does not exist in Harme.**

Every actor foreign key is already resolved server-side from `$request->user()`
and passed into the service layer as `$actor`. None are read from request input.
None are mass-assignable.

```
Controller: $this->results->validate($result, $request->user())
Service:    $result->validated_by = $actor->getKey();
```

Verified `$fillable` contents:

| Model | Actor-ish fillable columns |
|---|---|
| `LaboratoryResult` | **NONE** |
| `User` | **NONE** |
| `LaboratoryRequisition` | `requesting_clinician` ← *the one exposure* |
| `AuditLog` | `user_id`, `user_name` (written only by `AuditLogger`) |

### Actor columns that exist

| Table | Columns |
|---|---|
| `laboratory_requisitions` | `requesting_clinician` *(string)*, `requesting_department` *(string)*, `created_by`, `updated_by`, `cancelled_by` |
| `laboratory_results` | `performed_by`, `performed_at`, `validated_by`, `validated_at`, `unvalidated_by`, `unvalidated_at`, `last_printed_at`, `created_by`, `updated_by` |
| `audit_logs` | `user_id`, `user_name` |

### Work order for Phase 4

| Workflow | File | Line | Field | Current behaviour | DB column | Proposed replacement |
|---|---|---|---|---|---|---|
| Requisition create | `app/Http/Requests/Laboratory/RequisitionRequest.php` | 31 | `requesting_clinician` | **Free text from request**, `nullable\|string\|max:255`, mass-assignable | `laboratory_requisitions.requesting_clinician` | Resolve from authenticated Staff; retain column as historical snapshot; reject client value |
| Requisition create | `app/Http/Requests/Laboratory/RequisitionRequest.php` | 32 | `requesting_department` | **Free text from request** | `laboratory_requisitions.requesting_department` | Resolve from Staff→Department reference entity |
| Requisition create | `app/Services/Laboratory/RequisitionService.php` | 46–47 | `created_by` / `updated_by` | Server-resolved ✅ | FK | Add actor snapshot columns |
| Requisition submit/transition | `app/Services/Laboratory/RequisitionService.php` | 82, 116, 161 | `updated_by` | Server-resolved ✅ | FK | Add actor snapshot |
| Requisition cancel | `app/Services/Laboratory/RequisitionService.php` | 215–217 | `cancelled_by` | Server-resolved ✅ | FK | Add actor snapshot |
| Result create | `app/Services/Laboratory/ResultService.php` | 83–84 | `created_by`/`updated_by` | Server-resolved ✅ | FK | Add actor snapshot |
| Result entry | `app/Services/Laboratory/ResultService.php` | 132–135 | `performed_by` | Server-resolved ✅ | FK | Add actor snapshot |
| Result validation | `app/Services/Laboratory/ResultService.php` | 180–182 | `validated_by` | Server-resolved ✅ | FK | **Full immutable snapshot** (name/title/speciality) |
| Result unvalidation | `app/Services/Laboratory/ResultService.php` | 217–227 | `unvalidated_by` | Server-resolved ✅ | FK | Add actor snapshot |
| Printing | `app/Http/Controllers/Laboratory/ReportController.php` | 33, 46 | `recordPrint($result, $request->user())` | Server-resolved ✅ | `last_printed_at` only — **no `printed_by` column** | Add `printed_by` + snapshot |
| Report signatory | `resources/views/laboratory/reports/report.blade.php` | ~248 | `validatedBy?->name` | **Live join to current user** | — | Read from snapshot |

**So Phase 4 reduces to four real tasks:** (1) replace `requesting_clinician`/`requesting_department`
free-text entry, (2) add snapshot columns alongside the existing correct FKs,
(3) add the missing `printed_by` column, (4) switch the report signatory line
from a live join to the snapshot.

### Workflow steps the specification assumes that **do not exist**

Harme's actual result lifecycle is **entry → validation**. There is:

- **no separate review step** (`reviewed_by` does not exist)
- **no separate approval step** (`approved_by` does not exist)
- **no specimen entity** (collection is a status transition on the requisition, `collected_at`)
- **no `released_by`, `modified_by`, `printed_by`**

Per the directive *"Do not invent workflow states"*, these must **not** be created.

---

## 4. Authorization

- **Bespoke**, not a package. `roles`, `permissions`, `role_permissions`.
- `permissions` columns: `id, name, label, module, description, module_order, display_order`.
- `User::hasPermission(string $name)`, memoised per request; super-admin roles short-circuit.
- Single source of truth: `database/seeders/permissions-seed.php`, read through `app/Support/PermissionCatalogue.php`.
- Route enforcement via `permission:<name>` middleware; object-level via Policies.

### Established naming convention

```
dashboard.view
users.{view,create,update,delete,activate,deactivate}
roles.{view,create,update,delete,assign_permissions}
laboratory.requisition.{view,create,update,delete,submit,cancel}
laboratory.result.{view,create,update,delete,validate,unvalidate,print}
laboratory.test.{view,create,update,delete,activate,deactivate}
laboratory.panel.{view,create,update,delete,activate,deactivate}
laboratory.parameter.{view,create,update,delete,activate,deactivate}
```

43 permissions total. The pattern is `<module>.<entity>.<verb>` for laboratory
and `<plural-entity>.<verb>` for administration.

**Note:** the specification's candidate permissions (`staff.view`, `staff.physician.create`, …)
match the laboratory dotted style. New Staff permissions should be seeded through
`permissions-seed.php` so `PermissionCatalogue` picks them up automatically.

**Pre-existing defect found:** `app/Http/Controllers/DashboardController.php` checks
`user.view` and `role.view` (singular). The real permissions are `users.view` and
`roles.view`. These cards render today only because super-admin short-circuits.
Not caused by this work; flagged for a separate fix.

---

## 5. Audit

- Table `audit_logs`: `id, user_id, user_name, action, entity_type, entity_id, entity_label, description, metadata, ip_address, user_agent, created_at`.
- **No `updated_at`** — append-only by construction.
- `AuditLogger::record(AuditAction, ?Model, ?string, array, ?User)` is the single writer.
- `AuditAction` enum has **41 cases** covering user, role, test, panel, parameter, requisition and result events.
- `metadata` is a JSON column already used for before/after-style payloads (e.g. `previous_validated_by`).
- An activity feed already exists: `resources/views/components/activity-feed.blade.php`, fed by `DashboardController`.
- 85 audit rows currently exist.

### Can it support the required actor snapshot?

**Yes — and it already does so partially.** `user_name` is an existing actor-name
snapshot taken at write time. The extension required is additive:

```
actor_staff_id, actor_title, actor_speciality, actor_provenance
```

`user_id` → `actor_user_id` and `user_name` → `actor_name` are **renames of existing
production columns**, which the hard prohibitions forbid. **Recommendation: keep the
existing column names and add the new ones alongside.** Do not create a second audit system.

---

## 6. Enum / controlled vocabulary inventory

All ten are native PHP string-backed enums in `app/Enums/`:

| Enum | Values | Methods |
|---|---|---|
| `Gender` | `male, female, other, unknown` | `label()` `options()` |
| `RequisitionStatus` | `draft, submitted, collected, processing, completed, cancelled` | `label()` `options()` `badgeClasses()` |
| `RequisitionItemStatus` | `pending, collected, processing, resulted, validated, cancelled` | `label()` `badgeClasses()` |
| `RequisitionPriority` | `routine, urgent, stat` | `label()` `options()` `badgeClasses()` |
| `ResultStatus` | `pending, in_progress, completed` | `label()` `options()` `badgeClasses()` |
| `ValidationStatus` | `pending_validation, validated` | `label()` `options()` `badgeClasses()` |
| `Interpretation` | `normal, low, high, critical_low, critical_high, abnormal, positive, negative, not_applicable` | `label()` `options()` `badgeClasses()` |
| `ParameterDataType` | `numeric, text, boolean, positive_negative, dropdown` | `label()` `options()` |
| `TestResultType` | `single, parameterised` | `label()` `options()` |
| `AuditAction` | 41 cases | `label()` |

**Existing contract:** `value` (machine) + `label()` (display) + `options()` (form arrays).
There is **no** `sortOrder`, `active`, `searchKeywords` or `parent`. `Gender` already
exists and matches the specification's requirement.

**Value casing:** existing machine values are **lower_snake_case** (`pending_validation`),
not `UPPER_SNAKE_CASE`. The specification's examples use `INTERNAL_MEDICINE`.
See Conflict 5.

### Classification for new vocabulary

| Value set | Classification | Rationale |
|---|---|---|
| `Speciality`, `SubSpeciality` | **SYSTEM ENUM** — but see Conflict 5 | Finite, stable, clinically standardised |
| `StaffStatus`, `EmploymentType`, `Profession`, `PositionType` | SYSTEM ENUM | Finite, system-defined |
| `LicenseStatus`, `RegistrationStatus`, `PhysicianPracticeStatus`, `QualificationType` | SYSTEM ENUM | Finite |
| `UserAccountStatus` | **Already modelled** as `users.is_active` boolean — do not duplicate |
| **Department**, **Unit** | **CONFIGURABLE REFERENCE ENTITY** | Facility-specific. Note `laboratory_requisitions.requesting_department` is currently free text — no entity exists yet |
| `Priority`, `RequisitionStatus`, `ResultStatus` | **Already exist** — extend, never re-create |
| `SpecimenStatus`, `SpecimenType`, `ApprovalStatus`, `ReportStatus` | **DO NOT CREATE** — no such workflow exists in Harme |

---

## 7. Laboratory status inventory (actual)

```
Requisition:      draft → submitted → collected → processing → completed
                                                             ↘ cancelled
Requisition item: pending → collected → processing → resulted → validated
                                                              ↘ cancelled
Result:           pending → in_progress → completed
Validation:       pending_validation → validated   (reversible: unvalidated_by)
```

There is **no specimen lifecycle, no approval stage and no report stage.**

---

## 8. File storage

| Aspect | Finding |
|---|---|
| Default disk | `local` → `storage/app/private` (**private**) |
| Other disks | `public` → `storage/app/public`; `s3` configured from env |
| `storage:link` | **NOT run.** `public/storage` does not exist |
| Existing upload code | **NONE.** No `UploadedFile`, `->store()`, `storeAs()` or `Storage::` anywhere in `app/` |
| Image validation | None exists |
| Serving mechanism | None exists |

**Phase 3 photo handling is entirely greenfield.** There is no production convention
to conform to — which also means the specification's proposed path cannot conflict
with one, but see Conflict 2 on its suitability.

Existing file-naming convention for *references* (the nearest analogue):
`PREFIX-YYYYMMDD-00001` via `ReferenceNumberGenerator`.

---

## 9. Database / migration risk

| Metric | Count |
|---|---|
| `users` | **2** |
| users without a role | 0 |
| `roles` | 2 |
| `audit_logs` | 85 |
| `laboratory_requisitions` | 2 |
| …with `requesting_clinician` populated | **2** |
| `laboratory_results` | 8 |
| `laboratory_tests` / `laboratory_test_parameters` / `laboratory_panels` | 4 / 5 / 1 |

**Risk assessment: LOW.** This is a small dataset. The `users.staff_id` backfill
affects 2 rows. The historical actor backfill affects 8 results and 2 requisitions.

The specification's cautious four-step migration (nullable → backfill → verify → NOT NULL)
remains the correct sequence and is cheap here, but the "cannot pass step 3" scenario
is unlikely at this size.

**Note:** the database is `harme_laboratory` on MySQL. The dataset does **not** match
the figures in the reference screenshot shared earlier (54 tests / 182 parameters /
28 users) — that screenshot came from a different environment.

---

## 10. Speciality spelling audit

Searched `app/ resources/ database/ config/ routes/ tests/` for:
`specialty`, `Specialty`, `speciality`, `Speciality`, `MedicalSpecialty`,
`DoctorSpecialty`, `subspecialty`, `SubSpeciality`.

```
ZERO occurrences of every variant.
```

**No conflict.** New code is free to adopt the specification's canonical
`Speciality` / `SubSpeciality` without renaming anything. This resolves the
specification's flagged decision point outright.

---

## 11. Plan

### Proposed tables

| Table | Purpose |
|---|---|
| `staff` | Central staff record. `staff_id` (unique, immutable), `full_name`, `gender`, `date_of_birth`, `photo_path`, contact fields, `title`, `profession`, `speciality`, `sub_speciality`, `department_id`, `unit_id`, `position`, `professional_license`, `license_expiry`, `employee_id`, `employment_type`, `employment_status`, `supervisor_id`, timestamps, soft deletes |
| `departments` | Reference entity (replaces free-text `requesting_department`) |
| `units` | Reference entity |
| `staff_qualifications` | Repeatable qualifications (Phase 3) |
| `users.staff_id` | **Added column**, nullable → backfill → NOT NULL |

### Snapshot columns (Phase 4, additive)

On `laboratory_requisitions` and `laboratory_results`, per actor event:
`*_staff_id`, `*_actor_name`, `*_actor_title`, `*_actor_speciality`, `*_actor_provenance`.
Plus `printed_by` on `laboratory_results` (currently missing).

On `audit_logs`: `actor_staff_id`, `actor_title`, `actor_speciality`, `actor_provenance`
— **added alongside** the existing `user_id` / `user_name`, not renaming them.

### Actor resolver

New: `app/Services/AuthenticatedStaffResolver.php` returning an immutable
`app/Support/ActorIdentity.php` value object. Placed in `app/Services/` to match
the existing flat service convention. **Exactly one implementation**; every
workflow consumes it. `ActorIdentity::system()` covers console/queue.

### Phase file manifest (indicative)

- **Phase 1** — `app/Enums/{Speciality,SubSpeciality,StaffStatus,EmploymentType,Profession,PositionType,LicenseStatus,RegistrationStatus,PhysicianPracticeStatus,QualificationType}.php`; `app/Support/EnumRegistry.php`; `app/Rules/` or extended `Rule::enum()` usage; `resources/views/components/form/shared-enum-select.blade.php` (Alpine, matching the existing `investigationPicker`/`testPicker` pattern in `resources/js/app.js`); `app/Http/Controllers/EnumController.php`.
- **Phase 2** — `staff` + `departments` + `units` migrations; `users.staff_id` migration ×2; `app/Models/Staff.php`; `app/Services/StaffService.php`; `app/Services/AuthenticatedStaffResolver.php`; `app/Http/Controllers/Administration/StaffController.php`; `app/Policies/StaffPolicy.php`; Blade views under `resources/views/administration/staff/`; permissions appended to `database/seeders/permissions-seed.php`.
- **Phase 3** — `staff_qualifications`; physician controller/views; `app/Services/StaffPhotoService.php`; My Profile extension to the existing `ProfileController`.
- **Phase 4** — snapshot migrations; service-layer integration; UI replacement of `requesting_clinician`; report signatory switch.
- **Phase 5** — `docs/ACCEPTANCE-REPORT.md`; the feature test suite (note: **only stock example tests exist today**, so Phase 5 builds the suite from scratch).

### Testing strategy

PHPUnit feature tests under `tests/Feature/`. **There is currently no meaningful
test coverage** — `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php`
are the Laravel stubs, and the Feature one **fails** (asserts `/` returns 200; the
app redirects `/` to the dashboard, 302). This pre-dates the present work and
should be fixed or deleted early in Phase 1.

### Deployment / rollback

Additive migrations throughout; each phase's schema change is independently
reversible. The `users.staff_id` NOT NULL step is the only one-way door and is
gated on a verification query.

---

## Conflicts — RESOLVED 2026-09-20

All six were raised before any Phase 1 code was written. Maintainer decisions:

| # | Conflict | Decision |
|---|---|---|
| 1 | Staff ID generation | **Reuse the `ReferenceNumberGenerator` pattern** — unique index + caller retry. No second numbering architecture. |
| 2 | Photo storage | **Private disk + authorised endpoint** — `storage/app/private/staff/{staff_id}/photo.{ext}`, served through a permission-checked controller. Not the literal `.pic` path. |
| 3 | `users.name` | **Keep, synchronised from `staff.full_name`.** |
| 4 | Audit columns | **Keep `user_id` / `user_name`; add `actor_staff_id`, `actor_title`, `actor_speciality`, `actor_provenance` alongside.** No renames. |
| 5 | Enum value casing | **lower_snake_case** — house style wins (`internal_medicine`, not `INTERNAL_MEDICINE`). |
| 6 | Missing workflow stages | **Restrict Phase 4 to actors that exist**, and scope review / approval / specimen / modification-with-reason as a **separate project**. |
| 7 | Name change vs snapshot (raised 2026-09-20) | **Snapshots are absolute.** Once a record carries an actor snapshot, a later name change never rewrites it. A reprinted report always matches the filed copy. Live joins on work in progress continue to follow the current name. |

The original statements of each conflict follow, retained for the record.

---

## Conflicts requiring a maintainer decision

Per the CONFLICT RULE, these are raised rather than silently resolved.

### CONFLICT 1 — Staff ID generation

- **Existing implementation:** `app/Services/Laboratory/ReferenceNumberGenerator.php` derives the next sequence with `orderByDesc($column)->value($column)` + 1 inside the caller's transaction, relying on a **unique index plus caller retry** as the actual guarantee.
- **Requested specification:** *"Never use COUNT(\*) + 1 / MAX() + 1"* — while also requiring *"use the application's existing safe sequence approach."*
- **Why they conflict:** the existing approach **is** effectively MAX+1. The two instructions cannot both be honoured.
- **Impact:** Staff ID generation strategy; whether a new sequence mechanism is introduced (and therefore a second numbering architecture, which the prohibitions discourage).
- **Decision required:** reuse `ReferenceNumberGenerator`'s pattern (consistent, proven, unique-index-guarded), or introduce a dedicated counter table for `STF-000001`?

### CONFLICT 2 — Photo storage path and extension

- **Existing implementation:** none. Default disk is private `storage/app/private`; `storage:link` has not been run; no upload code exists.
- **Requested specification:** `Uploads/Staffs/{staffId}/ProfilePhoto.pic`.
- **Why they conflict:** there is no `Uploads/` root in this application, and `.pic` is not a real image media type. It would defeat the same specification's requirements to re-encode, set a safe MIME type and serve through an authorised endpoint.
- **Impact:** storage layout, serving endpoint, content-type handling, security posture.
- **Decision required:** adopt `storage/app/private/staff/{staff_id}/photo.{jpg|png|webp}` on the private disk served through an authorised controller (recommended, and satisfies the spec's security clauses), or implement the literal path?

### CONFLICT 3 — `users.name` retention

- **Existing implementation:** `users.name` is the only identity; `AuditLogger:38` snapshots it into all 85 audit rows; the printed report signatory reads `validatedBy->name`.
- **Requested specification:** *"Never fall back to users.name for workflow identity."*
- **Why they conflict:** every historical audit row and report already derives from `users.name`.
- **Impact:** backfill semantics and whether `users.name` remains as a denormalised mirror of `staff.full_name`.
- **Decision required:** keep `users.name` synchronised from `staff.full_name`, or deprecate it for workflow purposes while retaining it for authentication display?

### CONFLICT 4 — Audit column naming

- **Existing implementation:** `audit_logs.user_id`, `audit_logs.user_name`.
- **Requested specification:** `actor_user_id`, `actor_name`.
- **Why they conflict:** the hard prohibitions forbid renaming existing database columns.
- **Impact:** audit schema and every existing `AuditLogger` consumer.
- **Recommendation:** keep existing names, add `actor_staff_id` / `actor_title` / `actor_speciality` / `actor_provenance` alongside. **Confirm.**

### CONFLICT 5 — Enum value casing

- **Existing implementation:** machine values are lower_snake_case — `pending_validation`, `critical_high`, `positive_negative`.
- **Requested specification:** `INTERNAL_MEDICINE`, `GENERAL_PRACTICE` (UPPER_SNAKE_CASE).
- **Why they conflict:** introducing UPPER_SNAKE values creates two casing conventions in `app/Enums/`.
- **Impact:** stored values in `staff.speciality`, the enum API payload, validation messages.
- **Decision required:** follow the existing lower_snake_case house style (`internal_medicine`), or follow the specification literally?

### CONFLICT 6 — Workflow stages that do not exist

- **Existing implementation:** entry → validation. No review, approval, specimen, release or modification-with-reason stage.
- **Requested specification:** Phase 4 requires `reviewed_by`, `approved_by`, `released_by`, `modified_by`, specimen collection actors, and a mandatory modification reason.
- **Why they conflict:** the directive forbids inventing workflow states, but Phase 4 and the Phase 5 acceptance tests enumerate actors for stages Harme does not have.
- **Impact:** scope of Phase 4 and the actor-spoofing acceptance matrix.
- **Decision required:** restrict Phase 4 to the actors that exist (`created_by`, `updated_by`, `cancelled_by`, `performed_by`, `validated_by`, `unvalidated_by`, `requesting_clinician`, plus a new `printed_by`), or is adding review/approval stages genuinely in scope?

---

## Phase 0 exit criteria

| Criterion | Status |
|---|---|
| Full relevant codebase inspected | **PASS** |
| Laravel / PHP versions confirmed | **PASS** — 13.32.0 / 8.3.29 |
| No Livewire confirmed | **PASS** |
| Existing architecture documented | **PASS** |
| Existing Staff/User relationship documented | **PASS** — none exists; `users` has no `staff_id` |
| Manual actor-entry inventory complete | **PASS** — one real exposure (`requesting_clinician`) |
| Existing authorization documented | **PASS** |
| Existing audit mechanism documented | **PASS** |
| Existing enum mechanism documented | **PASS** |
| Existing file storage documented | **PASS** |
| Speciality spelling audit completed | **PASS** — zero occurrences |
| Migration risk identified | **PASS** — LOW (2 users, 8 results) |
| `docs/INTEGRATION-PLAN.md` created | **PASS** |
| `docs/IMPLEMENTATION-NOTES.md` updated | **PASS** |
| No production code changed | **PASS** |

**Phase 0 status: COMPLETE — BLOCKED on Conflicts 1–6 before Phase 1 begins.**
