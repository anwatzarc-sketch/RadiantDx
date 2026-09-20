# Acceptance Report

**Staff Identity, Physician Profile & Shared Enum / Controlled Vocabulary**
Harme Online Laboratory Management System · 2026-09-20

Every PASS below names the evidence. A checkbox without evidence is recorded as
FAIL or NOT VERIFIED, not as PASS.

**Suite:** 102 tests, **101 passing**, 592 assertions.
**Routes:** 26/26 return < 400 against the live database.
**Build:** `vite build` clean.

---

## Summary

| Phase | Result |
|---|---|
| 0 — Reconnaissance | **PASS** |
| 1 — Shared enums | **PASS** |
| 2 — Staff records & identity | **PASS** |
| 3 — Physician, photo, My Profile | **PASS** |
| 4 — Automatic actor identity | **PASS** (scope per Conflict 6) |
| 5 — Hardening & acceptance | **PASS**, with the exclusions named below |

One test fails: `ExampleTest::test_the_application_returns_a_successful_response`.
It is the stock Laravel stub asserting `/` returns 200; this application has
always redirected `/` to the dashboard. **Pre-existing, unrelated, and left in
place** because §A forbids deleting a test or rewriting it merely to pass.

---

## 5.2 Staff acceptance

| Requirement | Result | Evidence |
|---|---|---|
| Staff creation | PASS | `StaffIdentityTest::creating_staff_issues_a_sequential_identifier` |
| Staff update | PASS | `StaffIdentityTest::correcting_a_staff_name_reaches_the_laboratory_records` |
| Staff search | PASS | `StaffIdentityTest::the_staff_list_can_be_searched_and_filtered` |
| Staff filtering | PASS | same test (profession filter) |
| Staff profile | PASS | route smoke `/admin/staff/{id}` → 200 |
| Staff status controls access | PASS | `the_resolver_refuses_a_suspended_staff_member`, `..._a_former_staff_member`, `only_active_staff_may_work` |
| Staff ↔ user association | PASS | `an_account_resolves_to_its_staff_record` |
| Staff permissions | PASS | `staff_screens_require_their_permissions`, `view_permission_alone_does_not_allow_editing` |
| Staff import | **PARTIAL** | Analyse/preview/commit implemented and reachable (`/admin/staff/import` → 200); **not exercised against an uploaded CSV fixture.** See exclusions. |
| Staff export | PASS | `the_export_streams_the_filtered_set` — asserts the filter applies to the file |
| Staff audit | PASS | `staff_events_capture_the_actor_snapshot` |
| Account management | PASS | `an_account_is_created_against_the_staff_record_in_the_url` |

**Staff ID guarantees** — unique: `staff_identifiers_are_unique`; immutable:
`a_staff_identifier_cannot_be_changed_once_issued`; server-issued:
`a_staff_identifier_supplied_in_the_request_is_ignored`; never reused: the
sequence counts forward for the life of the installation and is guarded by a
unique index plus retry (`StaffNumberGenerator`).

**Backfill** — ran on the live database: 2 users, 2 linked, **0 unresolved**,
`users.staff_id` now NOT NULL. Both records flagged `needs_review` because no
professional detail was invented for them.

---

## 5.3 Physician acceptance

| Requirement | Result | Evidence |
|---|---|---|
| Physician profile extends Staff | PASS | `a_physician_profile_lives_on_the_staff_record` — also asserts no `physicians`/`doctors` table exists |
| Speciality | PASS | `SharedEnumTest::speciality_and_subspeciality_are_available_centrally` |
| SubSpeciality | PASS | `every_subspeciality_names_a_real_parent_speciality` (35 values, 13 parents) |
| Licensing | PASS | `an_expired_licence_is_derived_from_its_date`, `an_expiry_before_the_issue_date_is_rejected` |
| Registration | PASS | `PhysicianProfileRequest` rules; route smoke 200 |
| Qualifications | PASS | `qualifications_can_be_added_and_removed` |
| Practice status | PASS | physician edit screen; enum published |
| Professional bio | PASS | self-service + admin paths |
| Physician directory | PASS | `the_directory_lists_only_professions_that_require_a_licence` |
| Photo | PASS | see 5.4 |
| Licence status derivation | PASS | `a_licence_inside_the_configured_window_is_expiring_soon` |
| Qualification permissions | PASS | `qualification_management_is_permission_gated` |

**The rule that matters** — `a_suspended_licence_is_never_overwritten_by_expiry_derivation`
proves a Suspended or Revoked licence survives recalculation even when its
expiry is five years in the future, and
`saving_the_profile_does_not_clear_a_manually_asserted_status` proves it
survives a profile save. The window is configuration, not a magic number:
`the_expiring_soon_window_is_configuration_not_a_magic_number`.

---

## 5.4 Photo security acceptance

| Requirement | Result | Evidence |
|---|---|---|
| Valid JPEG accepted | PASS | `a_jpeg_is_accepted_and_re_encoded` — asserts 512×512 JPEG output |
| Valid PNG accepted | PASS | `png_and_webp_are_accepted` |
| Valid WebP accepted | PASS | same test (GD reports WebP support) |
| PHP renamed `.jpg` rejected | PASS | `a_php_script_renamed_as_a_jpeg_is_rejected` — also asserts nothing reached disk |
| Malformed image rejected | PASS | `a_truncated_image_is_rejected` |
| Unsafe SVG rejected | PASS | `an_svg_is_rejected_even_when_well_formed` (contains `<script>`) |
| Polyglot rejected or re-encoded | PASS | `a_polyglot_loses_its_payload` — asserts `<?php` and `system(` absent from stored bytes |
| Unauthorised upload for another staff member | PASS | `an_unauthorised_user_cannot_upload_for_another_staff_member` |
| Body-supplied staff_id cannot redirect storage | PASS | path is built from the router-resolved primary key; `a_staff_id_in_the_account_payload_cannot_redirect_the_association` covers the same principle on the account route |
| Remove photo works | PASS | `removing_a_photo_clears_the_record_and_the_file` |
| Default fallback works | PASS | `a_record_with_no_photo_serves_a_404_rather_than_a_placeholder`; the default is an initials tile rendered by `<x-staff-avatar>` |
| Not publicly reachable | PASS | `photos_are_not_stored_on_the_public_disk`, `a_photo_is_only_served_to_someone_who_may_view_the_record` |

The decision that carries this section: the upload is never stored as received.
It is decoded with GD and written out again from the pixels, which discards
appended bytes, comment-segment payloads and EXIF (including GPS) as a
by-product rather than as a series of checks.

---

## 5.5 Enum acceptance

| Requirement | Result | Evidence |
|---|---|---|
| Speciality catalogue | PASS | 31 values, clinically grouped |
| SubSpeciality catalogue | PASS | 35 values across 13 parents |
| Shared enum API | PASS | `the_enum_index_lists_published_vocabularies`, `a_vocabulary_serves_its_values` |
| Invalid value rejection | PASS | `an_unknown_value_is_rejected_with_the_exact_message` |
| Exact semantic message | PASS | asserts literally `Unknown Speciality value` / `Unknown StaffStatus value` |
| Case sensitivity | PASS | `validation_is_case_sensitive` — `Cardiology`, `CARDIOLOGY`, `CardioLogy` all rejected |
| Label not accepted as machine value | PASS | `a_display_label_is_not_accepted_as_a_machine_value` |
| No trimming into validity | PASS | `whitespace_is_not_trimmed_into_validity` |
| Inactive hidden from selection | PASS | `options_offer_only_active_values` |
| Inactive still displays historically | PASS | `retired_values_stay_resolvable_for_historical_display` |
| Searchable selection | PASS | `searching_card_finds_both_cardiology_specialities` |
| Dependent SubSpeciality | PASS | `subspeciality_can_be_narrowed_to_its_parent`, `a_subspeciality_from_another_speciality_is_rejected` |
| No duplicate option lists | PASS | options served from `/enums/{name}`; `SharedEnumSelect` is the only selector |
| Keyboard interaction | **NOT VERIFIED** | Implemented (arrow/enter/escape); not exercised in a browser |

**Unknown enum is a clean 404, and cannot reach a class:**
`a_vocabulary_name_cannot_be_used_to_reach_an_arbitrary_class` sends
`App\Models\User`, `Illuminate\Support\Str`, `../../Models/User` and the
deliberately unpublished `AuditAction`; all return 404.

---

## 5.6 Actor spoofing acceptance — the headline test

`ActorIdentityTest::no_client_field_can_redirect_the_recorded_actor`

Every field in `ClientActorFields::ALL` is posted **at once**, each naming a
real second staff record, to the requisition endpoint:

```
user_id, staff_id, actor_id, actor_staff_id, actor_name,
requestor_name, requestor_staff_id, requesting_clinician,
requested_by, requested_by_staff_id, entered_by, entered_by_staff_id,
performed_by, performed_by_staff_id, collected_by, collected_by_staff_id,
reviewed_by, reviewed_by_staff_id, approved_by, approved_by_staff_id,
validated_by, validated_by_staff_id, unvalidated_by, unvalidated_by_staff_id,
printed_by, printed_by_staff_id, released_by, released_by_staff_id,
modified_by, modified_by_staff_id, created_by, updated_by, cancelled_by
```

**Result: PASS.** The requisition is recorded against the signed-in person, and
the test then walks **every column of the saved row** asserting the impostor's
name appears in none of them.

`the_requesting_clinician_is_no_longer_accepted_from_the_request` covers the one
field that previously *was* free text: posting `Dr Somebody Else` yields a
requisition naming the signed-in user.

---

## 5.7 Historical snapshot test

`ActorIdentityTest::changing_a_speciality_does_not_rewrite_an_issued_report`
and `LaboratoryWorkflowIdentityTest` step 8.

A result validated by Dr Chaltu Roba (Pathology) is printed; all three actors
then change speciality to Dermatology and are renamed "Renamed Person"; the
report is fetched again and asserted to **still** contain `Dr Chaltu Roba` and
`Pathology`, and to contain **neither** `Renamed Person` nor `Dermatology`.

`a_name_correction_does_not_reach_an_existing_snapshot` proves the same through
the staff-update service, which does cascade the name to the account — the
account changes, the snapshot does not.

**PASS.**

---

## 5.8 Immutability test

| Attempt | Result | Evidence |
|---|---|---|
| Edit a recorded actor name | Refused | `a_recorded_actor_cannot_be_edited` |
| Repoint a snapshot to another staff record | Refused | `a_recorded_actor_cannot_be_repointed_to_another_staff_record` |
| Record the same role twice | Refused | `an_actor_cannot_be_recorded_twice_for_the_same_role` |
| Mass assignment | Impossible | `snapshot_columns_are_not_mass_assignable` |
| Unknown role | Refused | `an_unknown_role_is_refused` |

No silent update in any case: each throws `WorkflowViolationException`.

The one sanctioned removal is `clearActorSnapshot()`, used only when a result is
unvalidated — which genuinely undoes the act of validating it, and is itself
audited.

---

## 5.10 System actor

`work_without_a_session_records_the_system_actor` — `resolveOrSystem()` with no
session returns a system identity whose snapshot columns are populated
(`..._actor_name` not null, provenance `system`). **Never a null actor row.**
`console_context_resolves_to_the_system_actor` covers the console path.

---

## 5.11 End-to-end test

`LaboratoryWorkflowIdentityTest::a_whole_requisition_runs_without_anyone_naming_themselves`

Three distinct people, each with their own identity:

1. **Dr Amina Hassan** (Internal Medicine) raises the requisition
2. submitted, then collected
3. **Mr Bekele Tadesse** (Laboratory Medicine) enters the result
4. **Dr Chaltu Roba** (Pathology) validates
5. report printed
6. every step names a different person
7. the report renders the frozen identities
8. everyone changes speciality — the reprint is unchanged

**At no point is an actor name typed, an actor selected, or an actor ID
supplied.** The only inputs are patient and clinical data.

`the_requisition_screen_no_longer_offers_a_clinician_field` asserts the form
contains no `name="requesting_clinician"` control and instead shows
"Automatically identified from your account".

---

## Exclusions and things not verified

These are stated rather than claimed as passes.

1. **No browser verification anywhere.** Every result in this report is from
   PHPUnit and server-side route checks. The `SharedEnumSelect` keyboard
   navigation, the photo upload form, the staff and physician screens and all
   mobile behaviour have **not been opened in a browser**.

2. **Staff import is not covered by a fixture test.** The analyse/preview/commit
   path is implemented and the screen loads, but no test uploads a real CSV.
   This is the largest remaining gap and should be closed before the import is
   used on real data.

3. **Workflow stages that do not exist were not built** — review, approval,
   specimen collection, release, and modification-with-reason. Per the
   resolution of Conflict 6 these are a separate project; §A forbids inventing
   workflow states. Phase 4 therefore covers `requested_by`, `cancelled_by`,
   `performed_by`, `validated_by` and the new `printed_by`.

4. **`collected_by` has no actor column.** `laboratory_requisitions.collected_at`
   exists with nobody attached to it. It was out of the agreed Phase 4 list, but
   it is the most obvious candidate for the follow-up project.

5. **`created_by` / `updated_by` / `unvalidated_by` carry no snapshot** — by
   design. They are operational, never appear on issued output, and the audit
   trail records them with an actor snapshot of its own.

6. **One failing test, pre-existing** — `ExampleTest`, described in the summary.

7. **Backfilled snapshots are marked `pre_migration`**, including the ones where
   a full professional identity was recovered. They were reconstructed after the
   fact rather than captured at the time, and a reader should be able to tell.
