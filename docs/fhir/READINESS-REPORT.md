# Harme LIS — FHIR R4 Readiness Report

**Phase:** 0A — behaviour audit (read-only)
**Date:** 2026-09-22
**Repository commit:** `59fc51e` ("FHIR Transition cleanup one")
**Specification:** `docs/FHIR-PRECONDITIONS.md` §39 structure
**Authority order applied:** §1 — executable code > schema > tests > factories > config > documentation > assumption

Evidence markers used throughout:

| Marker | Meaning |
|---|---|
| `[exec]` | Observed by executing application code; output quoted |
| `[schema]` | Read from live database schema / migrations |
| `[read]` | Read from source; not executed |
| `UNKNOWN — NOT ESTABLISHED FROM REPOSITORY` | Per §39, not inferred |

Probes ran inside a transaction that was rolled back. No row survived. No source
file was modified in this phase.

---

## 1. Executive Summary

The LIS is internally coherent, strongly audited, and unusually disciplined about
actor identity. The obstacles to an honest FHIR projection are **not** code
quality; they are four places where the LIS does not currently know a fact that
FHIR would require it to state.

**Can the current LIS tell the truth about what happened?** (§43)

| Question | Answer |
|---|---|
| Who ordered, performed, validated, printed? | **Yes** — frozen snapshots with provenance |
| What was requested, and what was it called at the time? | **Yes** — item-level snapshots |
| What value was reported, and against what range? | **Yes** — parameter-level snapshots |
| Which patient? | **No** — no identity anchor |
| Which physical specimen? | **No** — no specimen entity |
| Who collected it, and when? | **Partly** — `collected_at` only; no collector exists |
| What did the previously released result say? | **No** — overwritten in place |

The last item is the most serious. §20 and §21 cannot be satisfied for any result
corrected before a revision store exists.

Gap classification per §38 is in section 15.

---

## 2. Repository Baseline

`[exec]` / `[read]`

| Item | Value |
|---|---|
| PHP | 8.3.29 (ZTS, Windows) |
| Laravel | 13.32.0 |
| Database | MariaDB **10.4.32** (not MySQL) |
| Auth | Session; `web` guard; `EnsureUserIsActive`, `EnsurePasswordIsChanged` middleware |
| Authorization | Permission table + `CheckPermission` middleware + policies |
| Session / Cache / Queue | `database` for all three |
| Front end | Blade + Alpine 3 + Tailwind 4 via Vite; no Livewire; no SPA |
| Tests | PHPUnit 12.5; SQLite `:memory:` |
| Routes registered | `web`, `commands` only — **no `routes/api.php`** |
| Locked packages | 110 |

**Capability absence confirmed** (§2). Searching all 110 locked packages for
`fhir|hl7|smart|sanctum|passport|jwt|oauth|rabbit|kafka|redis|pusher|dompdf|snappy|mpdf|tcpdf|wkhtml|guzzle|twilio|mailgun|ses|nexmo`:

```
matches: guzzlehttp/guzzle, guzzlehttp/promises, guzzlehttp/psr7, guzzlehttp/uri-template
```

Guzzle is a framework transitive dependency, not an integration. Therefore:

- No FHIR library. No HL7 library.
- No API authentication package (no Sanctum, Passport, JWT).
- No message broker. No notification service. No PDF generator.
- Production `composer.json` requires only `php`, `laravel/framework`, `laravel/tinker`.

`app/` contains no `Events`, `Listeners`, `Notifications`, `Jobs`, `Mail` or
`Observers` directories. The only `event()` call is Laravel's own `Lockout` in
`LoginRequest`. `User` uses `Notifiable` but nothing is ever sent.

**Migration state:** all migrations applied; latest is
`2026_05_01_000100_rename_staff_id_to_staff_code_on_staff_table`.

---

## 3. Actual Clinical Workflow

`[read]` The chain is exactly as §3 describes, and each arrow is enforced in a
service, not a controller.

```
Catalogue → Requisition → Requisition Item → Result → Result Parameter → Validation → Report
```

### Requisition lifecycle (§4)

States are declared once in `RequisitionStatus::allowedTransitions()`:

```
Draft → Submitted → Collected → Processing → Completed
  │        │           │            │
  └────────┴───────────┴────────────┴──────→ Cancelled
```

`Completed` and `Cancelled` are terminal. Only `Draft` is editable.

`[exec]` Illegal jumps are genuinely refused:

```
WorkflowViolationException: A requisition cannot move from Draft to Collected.
```

| Transition | Route | Service | Permission | Preconditions | Mutations | Audit |
|---|---|---|---|---|---|---|
| create | `POST requisitions` | `RequisitionService::create` | `laboratory.requisition.create` | — | number issued, status Draft, `requested_by` snapshot frozen | `requisition.created` |
| update | `PUT requisitions/{id}` | `::update` | `.update` | Draft only | items rebuilt | `requisition.updated` |
| submit | `POST {id}/submit` | `::submit` | `.submit` | Draft; ≥1 item | `submitted_at` | `requisition.submitted` |
| collect | `POST {id}/transition` | `::transitionTo` | `.update` | enum-legal | `collected_at`; **opens results**; items→Collected | `requisition.collected` |
| process | `POST {id}/transition` | `::transitionTo` | `.update` | enum-legal | `processing_at`; items→Processing | `requisition.processing` |
| complete | `POST {id}/transition` | `::transitionTo` | `.update` | **all items Validated or Cancelled** | `completed_at` | `requisition.completed` |
| cancel | `POST {id}/cancel` | `::cancel` | `.cancel` | not terminal; **no validated result exists** | `cancelled_at`, reason, `cancelled_by` snapshot; items→Cancelled | `requisition.cancelled` |
| delete | `DELETE {id}` | `::delete` | `.delete` | Draft; no results | soft delete | `requisition.deleted` |

Two automatic transitions exist and are **not** operator-driven:

- Validating the final outstanding item auto-completes the requisition
  (`ResultService::completeRequisitionIfFinished`).
- Unvalidating any result on a Completed requisition reopens it to `Processing`
  (`::reopenRequisitionIfCompleted`).

### Time and date semantics (§34)

`[read]` `config/app.php` `'timezone' => 'UTC'`; `APP_TIMEZONE` is not set in
`.env`. All `*_at` columns are `timestamp` cast to `datetime`.

Date-only versus dateTime is explicit in the casts:

| Cast `date` (no time component) | Cast `datetime` |
|---|---|
| `patient_date_of_birth`, `requested_date` | `submitted_at`, `collected_at`, `processing_at`, `completed_at`, `cancelled_at`, `performed_at`, `validated_at`, `unvalidated_at`, `last_printed_at` |

> **§34 rule:** `requested_date` is date-only. FHIR must emit it as `date`, not
> `dateTime`. Adding a time component would fabricate precision the domain never
> captured.

**Calendar system (§7):** `[exec]` A search of `app/`, `resources/` and `config/`
for `ethiopian|ethiopic|geez|amharic|calendar` returns exactly one file,
`resources/views/welcome.blade.php`, and that match is inside minified Tailwind
CSS in the unrouted stock Laravel landing page (`0` references in `routes/web.php`).

**There is no calendar conversion anywhere.** All dates are stored and rendered as
Gregorian.

> Whether staff at a Harer, Ethiopia site are entering Ethiopian-calendar dates
> into Gregorian fields is a clinical-safety question the repository cannot
> answer. **UNKNOWN — NOT ESTABLISHED FROM REPOSITORY.** See OQ-7.

---

## 4. Patient Identity

`[schema]` **No patient table exists.** Demographics are columns on
`laboratory_requisitions`:

```
patient_identifier   varchar   required by validation
patient_name         varchar   required by validation
patient_gender       varchar   nullable, cast to Gender enum
patient_date_of_birth date     nullable
patient_age_years    int       nullable
```

Answering §6 directly:

| Question | Answer |
|---|---|
| Does a patient table exist? | No |
| What identifies a patient? | `patient_identifier`, free text |
| Generated or entered? | **Entered.** No generator exists |
| Can identifiers be duplicated? | **Yes** — no unique index, no index at all |
| Can one patient have multiple identifiers? | No structure to express it |
| How are returning patients recognised? | **They are not.** No lookup, no matching |
| How are demographic conflicts handled? | **They are not detected** |

`[read]` `RequisitionRequest` requires either `patient_date_of_birth` or
`patient_age_years`, never both mandatory:

```php
if ($this->input('patient_date_of_birth') === null && $this->input('patient_age_years') === null) {
    $validator->errors()->add('patient_age_years', 'Record either the date of birth or the age of the patient.');
}
```

> **§7 rule engaged.** Age-only patients are a supported, validated path.
> `Patient.birthDate` **must not** be fabricated from `patient_age_years`.

**Name structure (§7):** `patient_name` is a single free-text field. There is no
given/family split anywhere. Ethiopian naming is patronymic, not
given/family. `Patient.name` must use `text` and must not be algorithmically
split.

Classification: **SIGNIFICANT DOMAIN CHANGE**.

---

## 5. Specimen Model

`[schema]` **No specimen entity exists.** The LIS knows exactly two facts:

```
specimen_type         free text, nullable, copied test → item → result
requisition.collected_at   one timestamp for the whole requisition
```

Against §8's checklist:

| Element | State |
|---|---|
| specimen identity | **Does not exist** |
| specimen type | DIRECTLY CAPTURED (free text) |
| collection date/time | DIRECTLY CAPTURED, but **requisition-level only** |
| collector | **Does not exist** — no column of any kind |
| accession number | **Does not exist** |
| received date/time | **Does not exist** |
| condition | **Does not exist** |
| container | **Does not exist** |
| body site | **Does not exist** |

**§15 specimen-to-order relationship:** The LIS cannot answer it. Two items with
`specimen_type = 'Serum'` on one requisition may or may not be one physical tube.
Per §15, this must not be inferred from matching text.

Any FHIR Specimen is therefore **DERIVED**, never **DIRECTLY CAPTURED**, and must
be labelled so.

Classification: **SIGNIFICANT DOMAIN CHANGE** for true specimen identity;
**SMALL DOMAIN CHANGE** for a collector column alone.

---

## 6. Actor / Provenance Model

### Snapshot mechanism

`[read]` `RecordsActorSnapshots` writes five columns per role
(`*_staff_id`, `*_actor_name`, `*_actor_title`, `*_actor_speciality`,
`*_actor_provenance`) and makes them **append-only**: an `updating` hook throws
if a written snapshot changes. `recordActor()` refuses to overwrite.
`clearActorSnapshot()` exists and is called in exactly one place — unvalidation.

Provenance states: `authenticated`, `system`, `pre_migration`.

### Coverage (§10)

| Event | FK | Snapshot | State |
|---|---|---|---|
| requested | — | **Yes** (`requested_by`) | Complete |
| cancelled | `cancelled_by` | **Yes** (`cancelled_by`) | Complete |
| performed | `performed_by` | **Yes** | Complete |
| validated | `validated_by` | **Yes** | Complete |
| printed | `printed_by` | **Yes** (first print only) | Complete |
| unvalidated | `unvalidated_by` | **No** | FK only |
| collected | **none** | **No** | **Concept absent entirely** |

> The asymmetry matters for scoping: unvalidation needs snapshot columns added to
> an existing FK; collection needs the actor concept introduced from nothing.

### Ordering actor (§9)

`[read]` `requesting_clinician` is **derived from authentication**, never typed
and never selected. `RequisitionService::create()`:

```php
$requestor = $this->identity->resolve($actor);
$requisition->recordActor('requested_by', $requestor);
$requisition->requesting_clinician = $requestor->displayName();
```

`AuthenticatedStaffResolver` is the sole path: session → User → `staff_id` →
Staff → `ActorIdentity`. It throws rather than degrading when the staff record is
missing or its status forbids work. There is **no external referring clinician
concept** and no field to hold one.

So ordering clinician, authenticated user and data enterer are **the same person
by construction**. §9's distinction cannot be represented today.

### Client-actor integrity (§11)

`[read]` `ClientActorFields::ALL` catalogues 28 field names —
`user_id`, `staff_id`, `requested_by`, `performed_by`, `collected_by`,
`validated_by`, `unvalidated_by`, `printed_by`, `created_by`, `cancelled_by`
and variants. `IgnoresClientActorFields` removes each from **both** the request
body and the query string.

| Clinical request | Strips actor fields? | Actor source |
|---|---|---|
| `RequisitionRequest` | **Yes** | derived from authentication |
| `ResultEntryRequest` | **Yes** | derived from authentication |
| `TransitionRequisitionRequest` | No | derived — controller passes `$request->user()` |
| `ReasonRequest` (cancel, unvalidate) | No | derived — controller passes `$request->user()` |

The two that do not strip have **no read path** for an actor field: their
controllers pass `$request->user()` explicitly and the services never consult the
payload. Not exploitable today, but the protection is narrower than the
catalogue implies. Worth aligning before FHIR writes are ever considered.

Classification: unvalidated snapshot **SMALL DOMAIN CHANGE**; collector
**SMALL DOMAIN CHANGE**; external referrer **SIGNIFICANT DOMAIN CHANGE**.

---

## 7. Catalogue and Panel Behaviour

### Catalogue (§12)

`[schema]` Five tables: `laboratory_tests`, `laboratory_test_parameters`,
`laboratory_test_parameter_options`, `laboratory_panels`,
`laboratory_panel_tests`.

| Property | State |
|---|---|
| Code ownership | Local, free text |
| Code uniqueness | `UNKNOWN — NOT ESTABLISHED FROM REPOSITORY` (census blocked, OQ-6) |
| Activation | `is_active` on tests, parameters, panels |
| Soft deletion | Tests, parameters, panels — **yes** |
| Parameter types | `numeric`, `text`, `boolean`, `positive_negative`, `dropdown` |
| Units / ranges | Free text plus `reference_low/high`, `critical_low/high`, `decimal_precision` |
| Result types | `single`, `parameterised` |

Catalogue is cleanly distinguishable from result: result rows carry their own
snapshot copies of every displayed catalogue value.

### Panel semantics (§14) — answered by execution

`RequisitionService::syncItems()` deletes **all** items and rebuilds them on every
create and update. Panel identity survives only as denormalised item columns.

`[exec]` Calling the service directly with `[panelA, panelB, sharedTest, panelA]`
where panelA = {shared, onlyA} and panelB = {shared, onlyB}:

```
items created: 7
 #1 PRB-SH source=panel panel_id=4 panel_code=PPA
 #2 PRB-A  source=panel panel_id=4 panel_code=PPA
 #3 PRB-SH source=panel panel_id=5 panel_code=PPB
 #4 PRB-B  source=panel panel_id=5 panel_code=PPB
 #5 PRB-SH source=test  panel_id=-  panel_code=-
 #6 PRB-SH source=panel panel_id=4 panel_code=PPA
 #7 PRB-A  source=panel panel_id=4 panel_code=PPA
panel A item rows: 4   (duplicate selection collapsed? NO)
shared test appears 4 times
```

Answering §14 point by point:

| Question | Answer |
|---|---|
| Panel selected? | Yes, as `panel:{id}` |
| Panel expanded? | Yes, at create/update |
| One item per member test? | Yes, active members only |
| Panel item retained? | **No** — no panel-level row exists |
| Panel result retained? | **No** — no panel-level result |
| Panel code snapshot? | Yes (`panel_code`) |
| Panel name snapshot? | Yes (`panel_name`) |
| Source type snapshot? | Yes (`source_type`) |
| Duplicate panel selection allowed? | **Depends on caller** — see below |
| Same test directly and via panel? | **Yes**, produces separate items |

**The de-duplication boundary.** Collapsing a repeated panel is a
*request-layer* guarantee only: it comes from `array_unique()` on the raw
`"type:id"` strings in `RequisitionRequest::selections()`. `[exec]` that layer
reduces the same input to `panel:4, panel:5, test:8`. The **service performs no
de-duplication at all**, so any non-HTTP caller — a future FHIR write, an
importer, a console command — can produce duplicate expansions.

**§14 historical rule — satisfied, with one caveat.** A historical panel order
*is* reconstructable from frozen item data alone (`laboratory_panel_id`,
`panel_name`, `panel_code`, `source_type`) without consulting today's panel
definition. The caveat is that `(requisition_id, laboratory_panel_id)` is a sound
parent-grouping key **only for HTTP-created requisitions**. There is no
per-expansion group identifier; `display_order` orders rows but does not identify
which expansion a row came from. See OQ-3.

Classification: **ADAPTER ONLY** if HTTP-only creation is accepted and enforced;
**SMALL DOMAIN CHANGE** if an explicit expansion-group id is required.

---

## 8. Result Semantics

### Two independent state fields (§5)

`[read]`

```
status:     Pending → InProgress → Completed      (derived, never set directly)
validation: PendingValidation → Validated          (independent)
```

`status` is computed in `deriveStatus()` from how many parameters carry values.

| §5 question | Answer |
|---|---|
| When does a result exist? | When the requisition reaches `Collected`; `openResultsFor()` opens one per non-cancelled item |
| When do values become complete? | When every parameter has a value |
| When is performer frozen? | First time status becomes `Completed`, if `performed_at` is null |
| When does validation occur? | Explicit operator action; requires completeness |
| Is validation irreversible? | **No** — `unvalidate()` exists, requires a reason |
| How does correction happen? | Unvalidate → re-enter → re-validate |
| What happens after unvalidation? | Item → `Resulted`; Completed requisition → `Processing` |
| Do previous values survive? | **No** — see section 9 |

Per §5, none of `edited = corrected`, `validated = interpreted`, or
`printed = released` holds here. In particular **validation is not
interpretation**: `validated_by` is the releasing authority, and the LIS asserts
nothing about clinical interpretation authorship (§28).

### Simple-test semantics (§13)

`[exec]` The synthetic parameter created for a `single` test:

```
catalogue_fk = NULL   data_type = numeric   name = <test name>
```

Answering §13 directly:

```
semantic code owner = laboratory_test          (test_name, test_code on the result)
value carrier       = synthetic result parameter (laboratory_test_parameter_id = NULL)
```

`data_type` is **hard-coded** to `numeric` in `materialiseParameters()` regardless
of the test, and a `single` test has no data-type column of its own. A laboratory
wanting a text-valued single test has no way to express it.

> Consequence: the null catalogue FK must **not** be read as "no semantic
> identity". The identity lives on the test.

### Value semantics (§16) and precision (§17)

`[exec]` `InterpretationEvaluator::toNumeric()` across the §16 test set:

| Input | `toNumeric()` | Implication |
|---|---|---|
| `12.50` | `12.5` | **precision lost in parsed form** |
| `12.5` | `12.5` | Quantity |
| `<0.5` | `0.5` | comparator lost |
| `>200` | `200.0` | comparator lost |
| `<=3` | `3.0` | comparator lost |
| `>=7` | `7.0` | comparator lost |
| `haemolysed` | `NULL` | not a Quantity |
| `trace` | `NULL` | not a Quantity |
| `1:80` | `NULL` | not a Quantity |
| `-3.2` | `-3.2` | Quantity |
| `1,5` | `1.5` | comma treated as decimal separator |
| `1e3` | `1000.0` | scientific notation accepted |

`[exec]` Storage, confirmed on MariaDB:

| Input | `result_value` (TEXT) | `result_numeric` `decimal(18,6)` |
|---|---|---|
| `12.50` | `'12.50'` | `'12.500000'` |
| `<0.5` | `'<0.5'` | `'0.500000'` |

**Three binding conclusions:**

1. **§17 precision.** `result_value` is the *only* carrier of written precision.
   `result_numeric` pads to six decimals and `toNumeric()` returns a float.
   `Quantity.value` must be rendered from the verbatim string. Routing through
   PHP float serialization destroys `12.50`.
2. **Comparator.** No comparator column exists. `Quantity.comparator` must be
   re-derived by re-parsing `result_value`.
3. **Non-numeric in numeric fields.** `ResultEntryRequest` rejects these at the
   HTTP layer ("must be a number"), but `ResultService::applyValue()` performs no
   such check — it stores verbatim and sets `result_numeric = null`. Same
   layering boundary as panels: HTTP prevents it, the service permits it. Whether
   any such rows exist is **UNKNOWN — NOT ESTABLISHED FROM REPOSITORY** (OQ-4).

### Interpretation safety (§18)

`[read]` Two distinct fields exist and the separation is already correct:

```
auto_interpretation  machine suggestion from InterpretationEvaluator::suggest()
interpretation       laboratory-reported value
```

`applyValue()` applies the suggestion to `interpretation` **only when the operator
has not chosen one**, and never modifies the entered value. Critical bounds
produce `CriticalLow` / `CriticalHigh` suggestions.

> **§18 rule engaged.** `auto_interpretation` must never be emitted as
> `Observation.interpretation`. Where the reported `interpretation` happens to
> equal the suggestion, it is emitted because the laboratory reported it, not
> because a bound was crossed.

### Reference-range history (§19)

`[read]` Ranges are **snapshotted at result creation** by `snapshotOf()`:
`reference_range`, `reference_low`, `reference_high`, `critical_low`,
`critical_high`, `unit`, `decimal_precision`. `BuildLaboratoryReport` reads only
these snapshots. A catalogue edit therefore **cannot** retroactively change a
released result — §19's hard rule already holds.

There is **no age/sex-specific range capability**. Ranges are single-valued per
parameter.

---

## 9. Correction / Revision Behaviour

This is the hardest precondition (§20, §21) and the LIS does not currently meet it.

`[exec]` A full validate → unvalidate → correct cycle:

```
after validate  : revision=1 validated_at=2026-09-21 23:33:49 snapshot=Khalid Ahmed
after unvalidate: revision=2 validated_at=NULL              snapshot=NULL
parameter value still: '12.50'
after correction:     '99.99'
parameter rows for this result: 1   (no revision table exists)
```

`[exec]` The unvalidation audit entry:

```json
{"reason":"probe correction","revision":2,
 "previous_validated_by":1,
 "previous_validated_at":"2026-09-21 23:33:49"}
```

Answering §20 exactly:

| Question | Answer |
|---|---|
| What happens on unvalidation? | `validation_status` → Pending; `validated_by`/`validated_at` nulled; **validator snapshot cleared**; `revision`++; reason stored |
| Are current values overwritten? | **Yes — in place, same row** |
| Are previous values retained? | **No** |
| Do revision numbers exist? | Yes — `int unsigned NOT NULL DEFAULT 1`, increments only on unvalidation |
| Does old performer/validator identity survive? | **Partly.** Validator *user id* and timestamp survive in `audit_logs`; the frozen staff snapshot does **not** |
| Does audit metadata contain complete previous values? | **No.** `result.updated` metadata carries counts and status, never values |

> **§20 verdict:** *"A revision number without revision data is not historical
> versioning."* That describes this system precisely. `revision` is a counter over
> data that no longer exists.

**§21 released-vs-live.** The three states are not distinguishable today:

```
LIVE RECORD                     = the single row
RELEASED RECORD                 = not stored
CORRECTED BUT UNRELEASED RECORD = the same single row
```

During the correction interval, an ordinary read returns the **unreleased
corrected value**. There is no deterministic answer to "what may an external
consumer see", because the previously released value is gone.

Consequences:

- `ReleasedLaboratoryResultResolver` can only be honest **from the moment an
  append-only revision store is writing**.
- For results corrected before that point, the last released revision is
  **permanently unrecoverable**. The conformance claim must say so rather than
  reconstruct it.
- FHIR `_history` / `vread` must not be promised until the store exists.
- This makes the revision store a **Phase 1 domain prerequisite**, not a later item.

Classification: **SIGNIFICANT DOMAIN CHANGE**.

---

## 10. Report / Printing Behaviour

`[read]` Answering §22:

| Question | Answer |
|---|---|
| What is a report? | A Blade HTML view laid out for A4 |
| Granularity | Per result, **or** per requisition (combined) |
| Generation | `BuildLaboratoryReport` assembles from **snapshots only** |
| Inclusion rules | Requisition report includes **validated results only**; single-result report includes the result regardless |
| Provisional shown? | Yes — `isProvisional` flag when an included result is unvalidated |
| Is the report stored? | **No.** Nothing is persisted; no PDF, no `presentedForm` source |
| Does printing mutate data? | **Yes** |

**§22's flagged issue is present.** Both report routes are `GET` and both call
`ResultService::recordPrint()`, which increments `print_count`, stamps
`last_printed_at`, and freezes the `printed_by` snapshot on first print. The
combined requisition report records a print for **every** validated result it
includes — so opening one page can write many rows.

§22 requires this be identified before printing is used to model Provenance. It is
identified here: **a GET currently means "print", and the distinction between
*view*, *print* and *release* does not exist in the routing.**

Classification: **SMALL DOMAIN CHANGE**.

---

## 11. Terminology State

`[schema]` Inventory per §24:

| Vocabulary | Location | Standard mapping |
|---|---|---|
| Test codes | `laboratory_tests.code` | **None** |
| Panel codes | `laboratory_panels.code` | **None** |
| Parameter codes | `laboratory_test_parameters.code` | **None** |
| Answer values | `laboratory_test_parameter_options.value/label` | **None** |
| Units | free-text `unit` columns | **None** |
| Specimen types | free-text `specimen_type` | **None** |
| Interpretation | `Interpretation` enum, 9 local cases | **None** |

There is **no** column anywhere for a standard code, a code system URI, a
verification state, a verifier or a verification timestamp. No LOINC, SNOMED CT
or UCUM value exists in the database or the codebase.

Per §24 every mapping therefore begins at `UNVERIFIED`, and per the
implementation prompt §1.3 nothing may be emitted as a coding until `VERIFIED`.

Classification: **TERMINOLOGY FOUNDATION** (new subsystem, not a domain change).

---

## 12. Identifier State

`[schema]` Per §25:

| Concept | Present | Notes |
|---|---|---|
| Database primary key | Yes | auto-increment `bigint` on every table |
| Business identifier | Yes | `requisition_number` `REQ-YYYYMMDD-00001`, `result_number` `RES-...`, `staff.staff_code` `STF-000001` |
| FHIR resource id | **No** | no `public_id`, no ULID/UUID column on any clinical table |
| Patient identifier | Weak | `patient_identifier` free text, **no index, no uniqueness** |
| External identifier | **No** | no concept |

`ReferenceNumberGenerator` derives the next value from the current maximum inside
the caller's transaction, relying on a unique index with retry
(`nextWithRetry`). Requisition and result sequences reset daily; `staff_code`
does not reset.

Per §25, auto-increment ids must not become public FHIR ids, and stable public
identifiers would need to be introduced additively.

Classification: **SMALL DOMAIN CHANGE**.

---

## 13. Security / API State

`[read]` Per §32/§33:

- **No API surface exists.** `bootstrap/app.php` registers `web` and `commands`
  only. There is no `routes/api.php`.
- Authentication is session-based. No token infrastructure is installed (§2).
- Every clinical route names its permission on `permission:` middleware, and that
  check is authoritative regardless of what the UI showed.
- Policies combine permission **with record state** — e.g.
  `LaboratoryResultPolicy::update()` requires the permission *and*
  `isEditable()` *and* a non-cancelled requisition. A permission alone never
  reopens a validated result.
- The only JSON endpoints are `/admin/enums` (allow-listed vocabularies) and the
  PWA manifest. Neither is patient identifying.
- `withExceptions` already declares JSON rendering for `api/*` and
  `expectsJson()` — a hook a future FHIR error model can use.

Security equivalence (§33) cannot be demonstrated because no FHIR surface exists
yet; the permission mapping in the implementation prompt §14 is consistent with
the existing permission names.

---

## 14. Test Coverage

`[exec]` Suite at this commit: **154 passed / 155**, 743 assertions. The single
failure is `ExampleTest::test_the_application_returns_a_successful_response` —
the stock Laravel scaffold expecting `/` to return 200 while the application
correctly redirects unauthenticated visitors to login.

Per §37, coverage by area:

| Area | Tests | Assessment |
|---|---|---|
| Staff identity / accounts | `StaffIdentityTest` 32, `UserStaffLinkageTest` 11, `UserRoleChangeTest` 4 | Strong |
| Physician profile | `PhysicianProfileTest` 17 | Strong |
| Staff photos | `StaffPhotoTest` 18 | Strong |
| Shared enums | `SharedEnumTest` 23 | Strong |
| PWA | `PwaTest` 12 | Strong |
| Actor identity | `ActorIdentityTest` 12 | Strong |
| Password controls | `PasswordControlsTest` 9 | Adequate |
| Footer / dashboard | 13 | Adequate |
| **Laboratory workflow** | `LaboratoryWorkflowIdentityTest` **2** | **Inadequate** |

The two laboratory workflow tests are
`a_whole_requisition_runs_without_anyone_naming_themselves` and
`the_requisition_screen_no_longer_offers_a_clinician_field`. Both assert **actor
identity**, not lifecycle correctness.

**Behaviours with no test coverage at all**, every one of which FHIR mapping
depends on:

```
illegal requisition transitions
cancel guards (validated-result block)
completion gating
panel expansion and duplicate handling
requisition item status synchronisation
result validation gating (completeness)
unvalidation, reason requirement, revision increment
auto-complete and reopen transitions
print counting and printed_by freezing
value parsing / precision / censored values
interpretation suggestion vs reported interpretation
soft-delete and retention behaviour
```

> **§37 rule engaged:** *"FHIR work must not assume that an untested behaviour is
> stable."* The probes in this report are currently the **only** verification for
> most of the above, and probes are not regression protection. Characterisation
> tests should precede Phase 1.

---

## 15. Gaps Blocking Honest FHIR

Classified per §38.

### SIGNIFICANT DOMAIN CHANGE

| Gap | Why | Blocks |
|---|---|---|
| **No patient identity** | No table, no matching, duplicable free-text identifier | `Patient`, every `subject` reference, `Patient` search |
| **No released-revision store** | Corrected values overwrite in place; prior values unrecoverable | §21 release semantics, `_history`, `vread`, `ReleasedLaboratoryResultResolver` |
| **No physical specimen identity** | Only type + requisition-level timestamp | `Specimen` as captured fact; §15 relationships |
| **No external referring clinician** | Requester is authenticated staff by construction | External-referral `ServiceRequest.requester` |

### SMALL DOMAIN CHANGE

| Gap | Why |
|---|---|
| Missing `collected_by` | Column does not exist; `collected` Provenance has no agent |
| Missing `unvalidated_by` snapshot | FK exists, snapshot columns do not |
| No public resource ids | Auto-increment must not be exposed (§25) |
| `patient_identifier` unindexed | `Patient?identifier` search cannot be served correctly |
| GET mutates print state | §22 requires view/print/release separation |
| Panel expansion-group id | Needed only if non-HTTP creation must be supported |
| Actor-field stripping incomplete | `ReasonRequest`/`TransitionRequisitionRequest` do not strip |

### ADAPTER ONLY

| Gap | Resolution |
|---|---|
| Decimal precision | Render `Quantity.value` from `result_value`, never from float |
| Comparator | Re-parse `result_value` |
| Simple-test coding | Take the code from `laboratory_test`, not the null parameter FK |
| Panel grouping (HTTP-created) | Group by `(requisition_id, laboratory_panel_id)` |
| Date vs dateTime | Emit `requested_date`/`patient_date_of_birth` as `date` |
| Reference ranges | Already snapshotted; read them |

### TERMINOLOGY FOUNDATION

No verified code exists for any test, panel, parameter, answer, unit, specimen
type or interpretation. All mappings start `UNVERIFIED`.

---

## 16. Production Behaviour That Must Not Change

These behaviours are load-bearing for clinical correctness. FHIR work must
preserve them.

1. **Actor snapshots are append-only and frozen.** A corrected staff name must
   not rewrite a historical record. The `updating` hook enforces this.
2. **Reports are built from snapshots, never the live catalogue.** A catalogue
   edit cannot retroactively alter an issued report.
3. **Actor identity is derived from the session only.** No client-supplied actor
   field is trusted anywhere in the clinical path.
4. **A validated result cannot be edited.** Correction must pass through
   unvalidation, which requires a reason and leaves an audit entry.
5. **A requisition with a validated result cannot be cancelled.**
6. **Illegal lifecycle jumps are refused** by the enum-declared transition table.
7. **Permission checks are server-side and authoritative**, and policies combine
   permission with record state.
8. **`auto_interpretation` never silently becomes the reported interpretation.**
9. **Soft-deleted parents, hard-deleted children.** `laboratory_requisitions`,
   `laboratory_results`, `laboratory_tests`, `laboratory_test_parameters`,
   `laboratory_panels`, `staff`, `users`, `departments`, `units` soft-delete.
   `laboratory_requisition_items` and `laboratory_result_parameters` have **no
   `deleted_at`** and are hard-deleted — by `syncItems()` on every requisition
   edit, and by `ResultService::delete()`. Per §23, clinical cancellation and
   deletion are **not** equivalent here, and a deleted draft's items are not
   reconstructable.

---

## 17. Open Questions

Per §41, these are hard stops requiring an owner decision before Phase 1.

| # | Question | §41 trigger |
|---|---|---|
| **OQ-1** | Patient identity: introduce a register, and what does `patient_identifier` actually mean today — MRN, national id, or ad-hoc? §6 forbids reinterpreting it as an MRN without evidence. | patient identity cannot be established |
| **OQ-2** | Are draft requisitions emitted as `ServiceRequest` at all? If yes, what supplies `authoredOn` when `submitted_at` is NULL? §27 warns against confusing requested / created / submitted / collected. | resource granularity ambiguous |
| **OQ-3** | Panel grouping: forbid service-layer duplicates and adopt `(requisition_id, panel_id)`, or add an explicit expansion-group id? | resource granularity ambiguous |
| **OQ-4** | Do non-numeric values exist in numeric parameters? HTTP rejects them, the service does not. Requires census. | — |
| **OQ-5** | Do any `pre_migration` actor snapshots exist? §30 forbids fabricating contemporaneous provenance for them. | actor provenance |
| **OQ-6** | Row census not taken — database server stopped mid-session (`3306 NOT listening`). Needed to size Phase 1 backfills and to close OQ-4 and OQ-5. | — |
| **OQ-7** | **Calendar.** No Ethiopian-calendar handling exists; all dates are Gregorian, app timezone UTC. Are staff at a Harer site entering Ethiopian-calendar dates into Gregorian fields? If so, every stored DOB and `requested_date` is suspect. | clinical safety |
| **OQ-8** | Release semantics during correction (§21): what may a consumer see between unvalidation and re-validation, given the previously released value no longer exists? | result release semantics ambiguous |
| **OQ-9** | Is retrieval of pre-correction values a consumer requirement? If yes, the revision store is mandatory before any `_history`/`vread` promise. | historical correction unknowable |
| **OQ-10** | External referral: is it in scope? Nothing in the domain supports it today. | resource granularity ambiguous |

---

## 18. Evidence Index

| Claim | Evidence | Location |
|---|---|---|
| Platform versions | `[exec]` `php -v`, `php artisan --version` | §2 |
| No FHIR/HL7/API-auth/broker/PDF dependency | `[exec]` scan of 110 locked packages | §2 |
| No events/notifications/jobs | `[read]` `app/` tree; `grep` for `event(`, `Mail::`, `Notification::` | §2 |
| Requisition transition table | `[read]` `RequisitionStatus::allowedTransitions()` | §3 |
| Illegal jump refused | `[exec]` `WorkflowViolationException` Draft→Collected | §3 |
| Date vs dateTime casts | `[read]` model `casts()` | §3 |
| No calendar conversion | `[exec]` grep; sole hit is minified CSS in unrouted `welcome.blade.php` | §3 |
| No patient table; age-or-DOB rule | `[schema]`, `[read]` `RequisitionRequest::withValidator()` | §4 |
| Specimen fields absent | `[schema]` column inspection | §5 |
| Snapshot roles and coverage | `[read]` `$actorSnapshotRoles`; `[schema]` column inspection | §6 |
| `collected_by` absent entirely | `[schema]` — only `collected_at` exists | §6 |
| Client-actor catalogue | `[read]` `ClientActorFields::ALL`; usage grep | §6 |
| Panel expansion, 7 items | `[exec]` service called directly | §7 |
| Request-layer `array_unique` | `[exec]` collapses to `panel:4, panel:5, test:8` | §7 |
| Simple-test synthetic parameter | `[exec]` `catalogue_fk=NULL data_type=numeric` | §8 |
| §16 value set | `[exec]` `InterpretationEvaluator::toNumeric()` across 14 inputs | §8 |
| Storage of `12.50` / `<0.5` | `[exec]` MariaDB round-trip | §8 |
| Ranges snapshotted at creation | `[read]` `ResultService::snapshotOf()` | §8 |
| Correction cycle | `[exec]` validate → unvalidate → correct | §9 |
| Unvalidation audit metadata | `[exec]` JSON quoted | §9 |
| No revision table | `[exec]` `information_schema` query | §9 |
| GET mutates print state | `[read]` `ReportController` → `recordPrint()` | §10 |
| No standard codes anywhere | `[schema]` column inspection | §11 |
| Identifier generators | `[read]` `ReferenceNumberGenerator`, `StaffNumberGenerator` | §12 |
| No `routes/api.php` | `[read]` `bootstrap/app.php` | §13 |
| Suite result 154/155 | `[exec]` `php artisan test` | §14 |
| Test counts per file | `[exec]` `#[Test]` count | §14 |
| Soft vs hard delete | `[read]` `use SoftDeletes`; `[schema]` `softDeletes()` in migrations | §16 |

---

## 19. Discrepancies (§1 format)

### D-1

```
DOCUMENTATION CLAIM    Implementation prompt §3.2 lists staff.staff_number
ACTUAL IMPLEMENTATION  Column is staff.staff_code (migration 2026_05_01_000100)
DISCREPANCY            staff_number does not exist in the schema
IMPACT                 Practitioner.identifier mapping would reference a
                       non-existent column. Correct MAPPING.md before Phase 3.
```

### D-2

```
DOCUMENTATION CLAIM    Implementation prompt §2.4 says "a simple test"
ACTUAL IMPLEMENTATION  TestResultType enum value is 'single'
DISCREPANCY            Token mismatch
IMPACT                 Cosmetic; MAPPING.md should use the real token.
```

### D-3

```
DOCUMENTATION CLAIM    Implementation prompt §3.7 maps submitted/collected/
                       processing/completed/cancelled to Task.status
ACTUAL IMPLEMENTATION  Draft is a real, reachable requisition state
DISCREPANCY            Draft has no mapping
IMPACT                 Either drafts are excluded from FHIR (decision) or a
                       Task.status is needed. See OQ-2.
```

### D-4

```
DOCUMENTATION CLAIM    Implementation prompt §3.5 maps submitted_at → authoredOn
ACTUAL IMPLEMENTATION  submitted_at is NULL until submission
DISCREPANCY            Draft orders have no authoredOn source
IMPACT                 Blocks ServiceRequest emission for drafts. See OQ-2.
```

### D-5

```
DOCUMENTATION CLAIM    Implementation prompt §2.6 — collection and unvalidation
                       "require additive actor-snapshot work"
ACTUAL IMPLEMENTATION  unvalidated_by exists as an FK with no snapshot columns;
                       collected_by does not exist in any form
DISCREPANCY            Understates collection
IMPACT                 Different sizing: one adds columns to an existing FK, the
                       other introduces the concept. Both are SMALL DOMAIN CHANGE.
```

### D-6

```
DOCUMENTATION CLAIM    Implementation prompt §2.2 — panels expand per selection
ACTUAL IMPLEMENTATION  True, but de-duplication exists only in the HTTP request
                       layer; RequisitionService de-duplicates nothing
DISCREPANCY            The safety of (requisition_id, panel_id) as a grouping key
                       is caller-dependent, not guaranteed
IMPACT                 Affects §3.6 parent-ServiceRequest design. See OQ-3.
```

---

## 20. Readiness Decision Gate (§40)

Unchecked. Each requires an explicit owner decision before implementation.

```
[ ] Patient identity model                  OQ-1   SIGNIFICANT
[ ] Patient identifier meaning              OQ-1   SIGNIFICANT
[ ] Specimen representation                 §5     SIGNIFICANT
[ ] Requester vs enterer semantics          §6     same person by construction
[ ] External referral semantics             OQ-10  SIGNIFICANT
[ ] Actor snapshot requirements             D-5    SMALL
[ ] Result release semantics                OQ-8   SIGNIFICANT
[ ] Revision/history requirement            OQ-9   SIGNIFICANT
[ ] Panel representation                    OQ-3   SMALL or ADAPTER
[ ] ServiceRequest granularity              OQ-2
[ ] Task granularity                        §29
[ ] DiagnosticReport granularity            §28
[ ] Reference-range behaviour               §19    already snapshotted
[ ] Terminology verification policy         §11    all UNVERIFIED
[ ] Public identifier strategy              §12    SMALL
[ ] API authentication boundary             §13    session only today
[ ] API read/write boundary                 §13    read-only recommended
[ ] Security permission mapping             §13
```

Additionally unresolved and not in §40's list: **OQ-7 (calendar)**. It is a
clinical-safety question, not a FHIR-design one, and it affects every stored date.

---

## 21. Phase Exit

```
Files created:
  docs/fhir/READINESS-REPORT.md

Files modified:
  (none — Phase 0A is read-only per §17 of the implementation prompt
   and §42 of the precondition specification)

Tests:
  php artisan test  ->  155 tests, 154 passed, 1 failed, 743 assertions
  Failure: ExampleTest::test_the_application_returns_a_successful_response
    Stock Laravel scaffold expects / to return 200; the application
    redirects unauthenticated visitors to login, so 302 is correct
    behaviour and the test is wrong. Pre-existing; unrelated to this phase.

Commands executed:
  php -v
  php artisan --version
  php artisan test
  php artisan tinker --execute   (behaviour probes, transaction rolled back)
  php -r                         (InterpretationEvaluator value-set probe)
  composer.lock dependency scan
  Get-NetTCPConnection           (database availability)

Known deviations:
  Row census not taken. The database server was stopped mid-session
  (3306 NOT listening), so OQ-4, OQ-5 and catalogue code-uniqueness are
  recorded as UNKNOWN — NOT ESTABLISHED FROM REPOSITORY rather than inferred.
  The §16 value-set probe was therefore run against the parser directly,
  which is engine-independent; storage behaviour was confirmed on MariaDB
  for two representative values before the server stopped.

Uncompleted work:
  Phase 0B (docs/fhir/MAPPING.md, docs/fhir/IMPLEMENTATION-NOTES.md)
  not started. Blocked on the §40 decision gate.

Open questions:
  OQ-1 .. OQ-10 in section 17. OQ-1, OQ-8 and OQ-9 are §41 hard stops.
```
