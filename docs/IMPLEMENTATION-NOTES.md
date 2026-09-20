# Implementation Notes

Running log for the Staff Identity, Physician Profile & Shared Enum feature.
Appended to at the end of every phase.

---

## Phase 0 — Reconnaissance

**Date:** 2026-09-20
**Production code changed:** none
**Files created:** `docs/INTEGRATION-PLAN.md`, `docs/IMPLEMENTATION-NOTES.md`

### Outcome

Full platform, identity, actor, authorization, audit, enum, storage and
migration-risk inventory completed. See `docs/INTEGRATION-PLAN.md`.

### Findings that change the shape of the work

1. **No Staff, Employee, Personnel, Physician or Doctor entity exists.**
   Phase 2 is greenfield rather than an extension. There is no competing Staff
   identity to reconcile.

2. **The actor-spoofing exposure the specification targets is mostly already
   closed.** All actor foreign keys (`created_by`, `updated_by`, `cancelled_by`,
   `performed_by`, `validated_by`, `unvalidated_by`) are assigned server-side
   from `$request->user()` in the service layer and are absent from `$fillable`.
   The single real exposure is `laboratory_requisitions.requesting_clinician`,
   a mass-assignable free-text field validated only as `string|max:255`.

3. **Harme's result lifecycle is entry → validation.** There is no review stage,
   no approval stage, no specimen entity, and no `printed_by`, `released_by` or
   `modified_by` column. Phase 4 and the Phase 5 acceptance matrix assume actors
   for stages that do not exist. Raised as Conflict 6.

4. **The audit trail already snapshots the actor name** (`audit_logs.user_name`,
   written by `AuditLogger`). The snapshot pattern the specification asks for
   exists in embryo and should be extended, not replaced.

5. **Speciality spelling: zero occurrences of any variant** anywhere in the
   repository. The specification's canonical `Speciality` / `SubSpeciality` can
   be adopted with no rename and no conflict.

6. **No file upload infrastructure of any kind exists**, and `storage:link` has
   not been run. Phase 3 photo handling is greenfield.

7. **Test coverage is effectively zero** — only the two stock Laravel
   `ExampleTest` files. `tests/Feature/ExampleTest.php` currently **fails**
   (asserts `/` returns 200; the application redirects `/` to the dashboard,
   302). Pre-existing; unrelated to this feature.

### Pre-existing defect noted in passing (not fixed)

`app/Http/Controllers/DashboardController.php` gates two dashboard cards on
`user.view` and `role.view`. The seeded permissions are `users.view` and
`roles.view`. The cards render today only because super-admin short-circuits
`hasPermission()`. A non-super-admin holding `users.view` would not see them.

### Blocked on

Conflicts 1–6 in `docs/INTEGRATION-PLAN.md` require maintainer decisions before
Phase 1 starts:

1. Staff ID generation strategy (existing `ReferenceNumberGenerator` pattern is
   MAX+1, which the specification prohibits)
2. Photo storage path and extension (`.pic` is not a real image type)
3. `users.name` retention
4. Audit column naming (renaming existing columns is prohibited)
5. Enum value casing (house style is lower_snake_case; spec uses UPPER_SNAKE)
6. Whether workflow stages Harme lacks are genuinely in scope

---

## Phase 1 — Shared Enum / Controlled Vocabulary

**Date:** 2026-09-20
**Tests:** 23 new, all passing (251 assertions). Full suite 24/25 — the one
failure is the pre-existing `ExampleTest` described under Phase 0.

### Approach

The application already had a working enum convention: string-backed PHP enums
exposing `label()` and a static `options()`. That convention was **extended, not
replaced**. `SharedEnum` adds only the four things a centrally served vocabulary
needs and a plain `label()` cannot carry — `sortOrder()`, `isActive()`,
`searchKeywords()` and `parent()` — and `ProvidesSharedEnumMetadata` supplies
defaults so a flat vocabulary need only implement `label()`.

The ten existing laboratory enums were **left untouched**. They are published
read-only through a compatibility shim in `EnumRegistry`, so a client has one
place to resolve every controlled value without any change to enums the
laboratory workflows depend on.

### Decisions applied

- Machine values are **lower_snake_case** (Conflict 5), matching all ten
  existing enums: `internal_medicine`, not `INTERNAL_MEDICINE`.
- `AuditAction` is deliberately **not published** — it enumerates internal event
  types and is of no use to a form.
- `UserAccountStatus` was **not created**; account state is already
  `users.is_active`, and a second representation would compete with it.
- Department and Unit were **not** made enums. They are facility-configurable
  reference entities and belong in tables (Phase 2).

### Security note

The vocabulary name in `/enums/{name}` is resolved through an explicit
allow-list and is **never** used to construct a class name. Covered by test:
`App\Models\User`, `Illuminate\Support\Str`, `../../Models/User` and the
unpublished `AuditAction` all return 404.

### Files created

| File | Purpose |
|---|---|
| `app/Support/Enums/SharedEnum.php` | The shared vocabulary contract |
| `app/Support/Enums/ProvidesSharedEnumMetadata.php` | Defaults + catalogue/selection helpers |
| `app/Support/Enums/EnumRegistry.php` | Name → implementation allow-list; legacy shim |
| `app/Enums/Speciality.php` | 31 clinical specialities, clinically grouped, with synonyms |
| `app/Enums/SubSpeciality.php` | 35 sub-specialities across 13 parent specialities |
| `app/Enums/StaffStatus.php` | Active/Inactive/Suspended/Former + `permitsSystemAccess()` |
| `app/Enums/EmploymentType.php` | Engagement basis |
| `app/Enums/Profession.php` | Discipline + `carriesSpeciality()` / `requiresLicence()` |
| `app/Enums/PositionType.php` | Post held |
| `app/Enums/LicenseStatus.php` | Licence standing + `isManuallyAsserted()` |
| `app/Enums/RegistrationStatus.php` | Professional-body registration |
| `app/Enums/PhysicianPracticeStatus.php` | Practising / on leave / retired |
| `app/Enums/QualificationType.php` | Qualification kinds |
| `app/Rules/SharedEnumValue.php` | Exact, case-sensitive validation; `Unknown {Enum} value` |
| `app/Rules/ValidSubSpeciality.php` | Server-side parent/child pairing enforcement |
| `app/Http/Controllers/EnumController.php` | `/enums` and `/enums/{name}` |
| `resources/views/components/form/shared-enum-select.blade.php` | The one reusable selector |
| `tests/Feature/SharedEnumTest.php` | 23 Phase 1 acceptance tests |

### Files modified

| File | Change |
|---|---|
| `routes/web.php` | Added the two `enums.*` routes inside `auth`+`active`, outside `password.changed` |
| `resources/js/app.js` | Added the `sharedEnumSelect` Alpine component |
| `docs/INTEGRATION-PLAN.md` | Recorded the six conflict resolutions |

### Not verified

The selector's **keyboard interaction and mobile behaviour are implemented but
have not been exercised in a browser.** Server-side behaviour, the catalogue
endpoint, validation and the parent/child rule are all covered by tests; the
interaction layer is not.

---

## Phase 2 — Staff Records and Staff ↔ User Identity

**Date:** 2026-09-20
**Tests:** 29 new (StaffIdentityTest), all passing. Full suite 52/53 — the one
failure remains the pre-existing `ExampleTest` described under Phase 0.
**Routes:** 21/21 green, including six new staff screens.

### Migration outcome on the live database

```
users=2 | with staff_id=2 | unresolved=0
staff records=2 | needs_review=2
  STF-000001  System Administrator  status=active  account=admin@example.com
  STF-000002  anwar bilcha          status=active  account=anwarbilcha2019@gmail.com
users.staff_id nullable? NO (required)
audit rows marked pre_migration=90
permissions=52 (staff.*=9)
```

The four-step sequence ran as specified: nullable column → backfill → verify →
NOT NULL. The verification step is a real gate, not a comment: it counts
unlinked accounts and throws with their email addresses rather than proceeding.

**No professional information was invented.** The backfill carried across only
the account's name and email; profession, speciality, title and department were
left null and both records flagged `needs_review`, with a banner on the profile
explaining why. A fabricated speciality on a laboratory report would be worse
than a blank one.

### Decisions applied

- **Staff ID** reuses the `ReferenceNumberGenerator` pattern (Conflict 1):
  highest-issued + 1 inside the caller's transaction, unique index as the real
  guarantee, `nextWithRetry()` for concurrent collisions. No second numbering
  architecture. Unlike the laboratory references it does not restart daily — a
  staff identifier is permanent.
- **Audit columns** added alongside the existing ones (Conflict 4):
  `user_id`/`user_name` untouched, `actor_staff_id`/`actor_title`/
  `actor_speciality`/`actor_provenance` added. The 90 pre-existing rows are
  marked `pre_migration` rather than left looking like resolution failures.
- **`users.name`** is kept and now mirrors `staff.full_name` on account creation
  (Conflict 3).

### The identity chain

`AuthenticatedStaffResolver` exists exactly once and is the only place that
walks session → User → staff_id → Staff → ActorIdentity. `ActorIdentity` is a
`final readonly` value object with three constructors: `fromStaff()`,
`system()` and `preMigration()`.

Every failure is explicit and there is **no fallback to `users.name`** — tested
directly: a suspended member of staff whose account name differs from their
staff name is refused, and the account name appears nowhere in the failure.

### Defence in depth on the association

Four independent layers, each tested:

1. `staff_id` absent from `$fillable` on both `User` and `Staff`.
2. `StaffRequest::prepareForValidation()` removes `staff_id` from the payload.
3. `StaffAccountRequest` strips `staff_id`, `user_id`, `name` and `full_name`;
   the staff record comes from the route.
4. Model `booted()` hooks refuse to change an issued `staff_id`, or to repoint
   an account at a different staff record.

The headline test posts `staff_id` naming a *different* staff record to the
account-creation endpoint and asserts the association follows the URL.

### Pre-existing guard found while testing

`Role::$is_super_admin` is not mass assignable, so `Role::create(['is_super_admin' => true])`
silently drops it. That is a sound production guard; the test helper now sets it
explicitly. Worth knowing before writing further tests.

### Files created

Migrations: `create_organisation_reference_tables`, `create_staff_table`,
`add_staff_id_to_users_table`, `backfill_staff_records_for_users`,
`require_staff_id_on_users`, `add_actor_snapshot_to_audit_logs_table`.

Models: `Staff`, `Department`, `Unit`.
Identity: `Support/ActorIdentity`, `Services/AuthenticatedStaffResolver`,
`Exceptions/StaffIdentityException`.
Services: `StaffService`, `StaffNumberGenerator`, `StaffImporter`.
HTTP: `StaffController`, `StaffAccountController`, `StaffImportController`,
`StaffExportController`, `StaffRequest`, `StaffAccountRequest`.
Policy: `StaffPolicy`. Factory: `StaffFactory`.
Views: `administration/staff/{index,create,edit,show,import}` + `partials/form`.
Tests: `StaffIdentityTest`, `Concerns/CreatesStaffUsers`.

### Files modified

`User` (staff relation, immutability hook), `AuditLog` (+4 fillable),
`AuditLogger` (actor snapshot), `AuditAction` (+8 staff events, 41→49),
`AuthorizationServiceProvider` (StaffPolicy), `permissions-seed.php` (+9),
`routes/web.php`, `sidebar.blade.php`, `UserFactory` (staff_id default),
`SharedEnumTest` (shared helper).

### Not verified

The staff screens have **not been exercised in a browser**. Server behaviour,
authorisation, the identity chain and the import/export paths are test-covered;
the rendered interface is not.

Import commit was not exercised against a real uploaded file in tests — the
analyse/commit logic is covered by the route smoke test only. Worth a fixture
test in Phase 5.

---

## Phase 2a — Name cascade and staff photographs

**Date:** 2026-09-20
**Tests:** 18 new (14 photo, 4 cascade/seeder). Full suite 70/71 — the one
failure remains the pre-existing `ExampleTest`.

### Name cascade

Reported symptom: correcting a staff name did not reach requisition and result
entries. The cause was narrower than it appeared. All nine actor display sites
read the name off the **account**, not the staff record:

```
report.blade.php:208   validatedBy?->name
report.blade.php:216   performedBy->name
report.blade.php:251   validatedBy?->name        (signatory line)
requisitions/show:116  cancelledBy?->name
requisitions/show:155  createdBy?->name
results/show:87,389,391,394
```

`users.name` was only written when the account was created, so it diverged the
first time a staff name was corrected:

```
staff.full_name=Khalid Ahmed   users.name=System Administrator   <-- DIVERGED
```

Fixed at the source: `StaffService::cascadeNameToAccount()` writes the
corrected name through to the account, and the laboratory records — which hold
a foreign key, not a copy — all resolve correctly. **One row is written, not
thousands.** Plus a one-off migration to repair the divergence already present.

**This interacts with Phase 4 and needs a decision — see below.**

### Two defects found while verifying

1. **`SuperAdminSeeder` overwrote the corrected name** on every run, from
   `config('laboratory.super_admin.name')`. The next deploy would have silently
   undone any correction. The seeder already reasoned this way about passwords
   ("an existing administrator keeps the password already in use"); the name now
   follows the same rule and is governed by the staff record.

2. **`SuperAdminSeeder` would have broken a fresh install.** It created a user
   without `staff_id`, which Phase 2 made NOT NULL. A clean
   `migrate && db:seed` would have failed. It now creates the staff record and
   links it. Verified against a throwaway SQLite database: seed OK, STF-000001
   issued and linked.

### Current vs historical, demonstrated

After the cascade, the split the specification asks for is already visible in
live data:

- laboratory screens and the printed report → follow the corrected name;
- the 126 existing `audit_logs` rows → still read "System Administrator",
  because `user_name` is a snapshot of who acted at that moment.

### Staff photographs

`StaffPhotoService` validates by **decoding**, not by inspecting claims. The
declared MIME type and the file extension are both ignored; only what
`getimagesize()` reports about the bytes is trusted. The file is then written
out again from the decoded pixels, which is what actually defeats the attack
class rather than merely testing for it — a polyglot loses everything that is
not a pixel, and EXIF (including GPS) goes with it.

Stored on the **private** disk at `staff/{id}/photo.jpg` — the path is built
from the primary key of the record the router resolved, never from input — and
served by an authorised controller. Per Conflict 2, not the literal
`Uploads/Staffs/{staffId}/ProfilePhoto.pic`.

The default is an initials tile rendered in type by `<x-staff-avatar>`, so
there is no placeholder file to deploy and the fallback stays legible at any
size.

Covered by test: PHP renamed `.jpg` rejected · SVG rejected · truncated image
rejected · polyglot re-encoded with its payload gone · oversized rejected ·
uploading for another staff member forbidden · self-service permission limited
to your own record · photo served only to those who may view the record ·
never written to the public disk.

### Files created

`Services/Administration/StaffPhotoService`,
`Http/Controllers/Administration/StaffPhotoController`,
`components/staff-avatar.blade.php`,
`migrations/..._resync_account_names_with_staff_records`,
`tests/Feature/StaffPhotoTest`.

### Files modified

`StaffService` (name cascade), `StaffPolicy` (managePhoto),
`SuperAdminSeeder` (staff record + name ownership), `permissions-seed.php`
(+2 photo permissions, 52→54), `routes/web.php` (3 photo routes),
`staff/index.blade.php` (photo column), `staff/show.blade.php` (photo card),
`StaffIdentityTest`.

### Open decision — cascade vs snapshot in Phase 4

§F 4.2 and 4.3 require historical actor identity to be frozen at the moment of
the action, so a report reprinted later matches the copy that was filed. The
cascade implemented here is the correct behaviour *while identity is resolved
by live join*, which is how the system works today. Once Phase 4 writes
snapshots, already-validated results will stop following later name changes.

That is the specification's intent, but it means a **typo** corrected after
validation will not reach reports already issued. Decision needed before Phase 4.

---

## Phase 3 — Physician profile, licensing, qualifications, My Profile

**Date:** 2026-09-20 · **Tests:** 17 new, all passing.

Physician is an extension of the staff record, not a second identity — asserted
by a test that no `physicians` or `doctors` table exists. The directory is
derived from `Profession::requiresLicence()` rather than a separate flag.

`LicenseStatusDeriver` keeps status in step with the expiry date under two
rules: a status a person asserted (Suspended, Revoked) is never overwritten by
a date, and the "expiring soon" window is configuration
(`laboratory.licensing.expiring_soon_days`), not a number in the code.

**My Profile** shows the identity as the laboratory sees it, read-only, with an
explicit self-editable allow-list in `UpdateOwnStaffProfileRequest::SELF_EDITABLE`
— an allow-list rather than fields omitted from a form, because omitting a
field stops it being shown, not being posted. The headline test posts every
administrative field at once and asserts none of them moves.

## Phase 4 — Automatic actor identity

**Date:** 2026-09-20 · **Tests:** 12 new, all passing.

Scope per Conflict 6: the actors that exist. Snapshot roles are
`requested_by`, `cancelled_by` (requisitions) and `performed_by`,
`validated_by`, `printed_by` (results). `printed_by` is new — there was a
`last_printed_at` with nobody attached to it.

`RecordsActorSnapshots` is the reusable mechanism; immutability is enforced in
`updating`, so a stray `fill()`, an import or a console session all hit the same
wall. The one sanctioned removal is unvalidating a result, which genuinely
undoes the act of validating it.

`requesting_clinician` — the single free-text actor field the reconnaissance
found — is no longer accepted. It is resolved from the session and frozen.
`ClientActorFields::ALL` lists 33 fields that are stripped before validation,
deliberately including names with no column behind them so that adding one later
cannot open the hole.

**Backfill:** 12 results and 3 requisitions given snapshots, all marked
`pre_migration` — full identities were recoverable through the user→staff link,
but they were reconstructed afterwards rather than captured at the time, and the
provenance says so.

## Phase 5 — Acceptance

**Date:** 2026-09-20 · `docs/ACCEPTANCE-REPORT.md`.

**102 tests, 101 passing, 592 assertions. 26/26 routes OK.**

The end-to-end test runs a requisition through three different people without
any of them naming themselves, then changes all their specialities and asserts
the reprint is unchanged.

Named exclusions rather than silent gaps: no browser verification anywhere;
staff import has no CSV fixture test; review/approval/specimen stages were not
built (separate project); `collected_by` still has no actor column; one
pre-existing `ExampleTest` failure left in place.
