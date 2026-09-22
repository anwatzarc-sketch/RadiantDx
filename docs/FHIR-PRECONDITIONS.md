# Harme LIS — FHIR Transition Preconditions & Readiness Specification

## Purpose

Before implementing HL7 FHIR R4, establish that the existing Harme Laboratory
Information System has a sufficiently explicit, stable, and clinically honest
domain model for FHIR to represent.

This document is a **precondition specification**, not an implementation prompt.

The agent must not begin FHIR schema changes, transformers, API routes, terminology
mapping, or FHIR-specific migrations until the applicable preconditions below
have been verified.

The objective is not to make the LIS look like FHIR.

The objective is:

> Make the existing clinical record sufficiently explicit and historically
> truthful that FHIR can represent it without inventing facts that the LIS does
> not actually know.

---

# 1. SOURCE-OF-TRUTH ORDER

When determining system behaviour, use this authority order:

```text
1. Executable application code
2. Database schema and constraints
3. Automated tests
4. Seeders / factories
5. Configuration
6. Documentation
7. Assumption
```

Never use assumption when a higher source can establish the answer.

Where sources disagree, report:

```text
DOCUMENTATION CLAIM
ACTUAL IMPLEMENTATION
DISCREPANCY
IMPACT
```

---

# 2. REPOSITORY READINESS

The repository must first be inspected to establish:

```text
PHP version
Laravel version
database engine/version
authentication mechanism
authorization mechanism
session/cache/queue configuration
front-end architecture
installed dependencies
test framework
route registration
migration state
seed/permission model
```

Confirm especially whether the system already contains:

```text
FHIR libraries
HL7 libraries
API authentication packages
message brokers
HTTP integrations
notification systems
PDF generators
```

Do not assume a capability exists because Laravel supports it.

Do not add a dependency merely because it would make an implementation easier.

---

# 3. CLINICAL DOMAIN READINESS

Establish the actual laboratory lifecycle.

At minimum, document:

```text
Catalogue
   ↓
Requisition
   ↓
Requisition Item
   ↓
Result
   ↓
Result Parameter
   ↓
Validation
   ↓
Report
```

For every major entity, document:

```text
creation
editing
state changes
terminal states
deletion/cancellation
authorization
audit behaviour
actor identity
timestamps
```

The laboratory lifecycle must not be inferred from table names.

---

# 4. REQUISITION LIFECYCLE

Identify the exact legal states and transitions.

For each transition record:

```text
route
controller/action
service/action
permission
preconditions
database mutations
timestamps
actor
audit event
```

The agent must explicitly establish:

```text
draft
submitted
collected
processing
completed
cancelled
```

or the actual states implemented by the repository.

The FHIR mapping must not invent lifecycle stages that the LIS does not have.

---

# 5. RESULT LIFECYCLE

Establish separately:

```text
result status
validation status
correction/revision behaviour
printing behaviour
```

Determine:

```text
when a result exists
when values become complete
when performer is frozen
when validation occurs
whether validation is irreversible
how correction happens
what happens after unvalidation
whether previous values survive
```

Do not assume:

```text
edited = corrected
validated = interpreted
printed = released
```

unless the LIS actually behaves that way.

---

# 6. PATIENT IDENTITY

FHIR Patient requires an identity anchor.

Before FHIR implementation, determine:

```text
Does a patient table exist?
What identifies a patient?
Is the identifier generated or entered?
Can identifiers be duplicated?
Can the same patient have multiple identifiers?
How are returning patients recognised?
How are demographic conflicts handled?
```

If the current LIS contains patient data only on requisitions, decide whether a
patient register is required.

Minimum acceptable target:

```text
stable patient identity
stable identifier(s)
requisition → patient relationship
requisition-time demographic snapshot retained
```

Rules:

```text
Never merge patients by name alone.
Never silently merge conflicting identifiers.
Never generate an exact DOB from age.
Never reinterpret an unknown identifier as an MRN without evidence.
```

---

# 7. PATIENT DEMOGRAPHIC SAFETY

Determine exactly what demographic data the LIS captures:

```text
name
sex/gender
DOB
age
phone
address
other identifiers
```

Determine whether dates are:

```text
Gregorian
Ethiopian calendar
mixed
converted
display-only
```

If age exists without DOB:

```text
FHIR Patient.birthDate MUST NOT be fabricated.
```

If an estimated DOB is introduced, the implementation must explicitly represent
that it is estimated rather than presenting it as exact.

Names must not be algorithmically split into Western first/family-name semantics
when the source naming structure does not support that interpretation.

---

# 8. SPECIMEN READINESS

Determine whether a true specimen entity exists.

Establish:

```text
specimen identity
specimen type
collection date/time
collector
accession number
received date/time
condition
container
body site
```

If the LIS currently knows only:

```text
specimen_type
requisition.collected_at
```

do not pretend that the physical specimen identity is known.

Where a logical Specimen must be derived for interoperability, explicitly document:

```text
DIRECTLY CAPTURED
DERIVED
UNKNOWN
```

A derived specimen must never be represented as though the LIS captured the
physical specimen identity.

---

# 9. ORDERING ACTOR

Determine exactly who places a laboratory order.

Distinguish:

```text
ordering clinician
authenticated user
person entering data
external referring clinician
```

Determine whether:

```text
requesting_clinician
```

is:

```text
typed text
selected staff
derived from authentication
display-only
```

The server must not trust client-submitted actor identity when the application
already has an authenticated staff resolver.

---

# 10. ACTOR PROVENANCE

For every clinically meaningful actor, establish:

```text
identity source
staff/user relationship
snapshot behaviour
timestamp
provenance state
audit behaviour
```

At minimum consider:

```text
requester
collector
performer
validator
unvalidator
printer
canceller
```

Where actor snapshots exist, historical FHIR output must use those snapshots.

Live staff records must not rewrite historical actor identity.

---

# 11. CLIENT-ACTOR INTEGRITY

Search all clinical routes, forms and request objects for client-controlled
identity fields.

Determine whether requests can submit:

```text
user_id
staff_id
requested_by
performed_by
validated_by
collector
validator
```

For each one, establish whether the server:

```text accepts it
ignores it
overwrites it
derives it from authentication
```

No privileged actor field may be trusted merely because it is hidden or
read-only in a Blade form.

---

# 12. CATALOGUE READINESS

Determine the authoritative catalogue model:

```text test
parameter
parameter option
panel
panel membership
```

Determine:

```text code ownership
code uniqueness
activation/deactivation
soft deletion
parameter types
units
reference ranges
critical ranges
turnaround
specimen type
```

The catalogue must be distinguishable from the patient-specific result.

---

# 13. SIMPLE-TEST SEMANTICS

Explicitly determine how simple tests are represented.

For example:

```text Blood Glucose
Creatinine
```

may have no true catalogue parameter while results still contain a synthetic
parameter.

The semantic identity of a simple test must not be lost during FHIR conversion.

The mapping must explicitly distinguish:

```text semantic code owner
value carrier
```

For Harme, establish whether:

```text laboratory_test = semantic code owner
synthetic result parameter = value carrier
```

before designing Observation transformation.

---

# 14. PANEL SEMANTICS

Determine exactly what happens when a panel is ordered.

Establish:

```text panel selected?
panel expanded?
one item per member test?
panel item retained?
panel result retained?
panel code snapshot?
panel name snapshot?
source type snapshot?
duplicate panel selection allowed?
same test selected directly and through panel?
```

The key historical rule is:

> A historical panel order must be reconstructable from the requisition-time
> frozen data, without consulting today's panel definition.

If this is impossible, the gap must be classified before FHIR implementation.

---

# 15. SPECIMEN-TO-ORDER RELATIONSHIP

Determine whether:

```text one order → one specimen
one order → many specimens
many orders → one specimen
```

is actually known by the LIS.

Do not infer physical specimen relationships from matching text such as
`"Serum"`.

---

# 16. RESULT VALUE SEMANTICS

For every supported result type determine:

```text numeric
text
boolean
positive/negative
dropdown
```

and establish:

```text source value
stored value
parsed value
display value
unit
precision
interpretation
```

For numeric values specifically, test:

```text 12.50
12.5
<0.5
>200
<=3
>=7
haemolysed
trace
1:80
```

Determine what goes into:

```text result_value
result_numeric
auto_interpretation
interpretation
```

Never let FHIR transformation silently change the meaning of the stored value.

---

# 17. DECIMAL PRECISION

Determine whether laboratory values carry clinically meaningful written
precision.

If the source stores:

```text 12.50
```

the interoperability representation must preserve that representation when the
clinical meaning depends on it.

Do not route clinically significant decimal values through floating-point
serialization without testing the result.

---

# 18. INTERPRETATION SAFETY

Determine:

```text how auto_interpretation is generated
how manual interpretation is selected
what values interpretation can contain
whether critical values have distinct interpretation states
```

Separate:

```text machine suggestion
laboratory-reported interpretation
```

The machine suggestion must never silently replace the laboratory's reported
interpretation.

Do not derive an FHIR interpretation code purely from a reference/critical range
unless the domain explicitly says that the resulting classification is the
reported laboratory interpretation.

---

# 19. REFERENCE RANGE HISTORY

Determine whether reference ranges are:

```text live catalogue data
snapshotted at entry
snapshotted at completion
recalculated at report time
```

A released historical result must retain the range that applied when it was
performed/reported.

If the LIS currently has only one generic range and the product requirement
promises age/sex-specific ranges, decide whether that is a domain prerequisite
or a future scope item.

Never let a catalogue edit retroactively change a released laboratory result.

---

# 20. CORRECTION AND REVISION HISTORY

This is a hard precondition for historical FHIR versioning.

Determine:

```text what happens on unvalidation
whether current values are overwritten
whether previous values are retained
whether revision numbers exist
whether old performer/validator identity survives
whether audit metadata contains complete previous values
```

A revision number without revision data is not historical versioning.

If consumers must retrieve pre-correction values, an append-only revision/history
model is required before promising FHIR `vread` or `_history`.

---

# 21. RELEASED-VS-LIVE DATA

Explicitly define:

```text LIVE RECORD
RELEASED RECORD
CORRECTED BUT UNRELEASED RECORD
```

For example:

```text
Validated revision 1
        ↓
Unvalidated
        ↓
Corrected values entered
        ↓
Not yet validated
```

Determine what an external FHIR consumer is allowed to see during that interval.

The answer must be deterministic.

A corrected but unvalidated value must not accidentally replace a previously
released result in ordinary FHIR reads.

---

# 22. REPORT SEMANTICS

Determine:

```text what is a report
report granularity
report generation method
result inclusion rules
whether provisional results can be shown
whether printing mutates data
whether a report is stored
```

Distinguish:

```text report view
report print
report release
```

A GET endpoint that mutates print counters must be identified as a domain/API
issue before it is used to model Provenance.

---

# 23. DELETE AND RETENTION

Determine:

```text what can be deleted
who can delete it
hard vs soft delete
cascade behaviour
whether deleted clinical records remain reconstructable
```

Clinical cancellation and clinical deletion must not be treated as equivalent.

FHIR deletion semantics must be based on what the LIS actually retains.

---

# 24. TERMINOLOGY READINESS

Before introducing standard coding, inventory:

```text local test codes
local parameter codes
local answer values
units
specimen types
interpretation values
```

For each standard mapping determine:

```text source value
candidate standard code
verification state
verifier
verification timestamp
```

Allowed state model:

```text
UNVERIFIED
VERIFIED
REJECTED
```

Only VERIFIED mappings may be emitted.

Never place guessed LOINC, SNOMED CT or UCUM codes into production data.

---

# 25. IDENTIFIER READINESS

Separate these concepts:

```text database primary key
FHIR resource id
business identifier
patient identifier
external identifier
```

Decide which must be:

```text stable
public
globally unique
human-readable
editable
immutable
```

A database auto-increment ID should not automatically become a public FHIR ID.

If stable public IDs are required, introduce them additively.

---

# 26. RESOURCE BOUNDARY READINESS

Before writing transformers, explicitly approve the resource boundary.

Candidate R4 resources:

```text Patient
Practitioner
PractitionerRole
Organization

CodeSystem
ValueSet

ServiceRequest
Task
Specimen
Observation
DiagnosticReport
Provenance
AuditEvent
```

Do not introduce resources merely because they exist in FHIR.

Every resource must have:

```text clear semantic purpose
source domain object
required R4 elements
historical source
authorization rule
search strategy
test strategy
```

---

# 27. SERVICE REQUEST READINESS

Before implementing ServiceRequest, establish:

```text what constitutes an order
when it becomes authored
what date represents occurrence
who requested it
what code identifies the ordered test
how subject/patient is referenced
```

Do not confuse:

```text requested date
creation timestamp
submission timestamp
collection timestamp
```

They represent different events.

---

# 28. DIAGNOSTIC REPORT READINESS

Before implementing DiagnosticReport, determine:

```text report boundary
result boundary
validation boundary
responsible performer
responsible interpreter
issued time
linked observations
```

Do not automatically equate:

```text validator = clinical interpreter
```

unless the actual LIS establishes that relationship.

---

# 29. TASK READINESS

If Task is introduced, define exactly what it represents.

It must not become a vague duplicate of ServiceRequest.

Use:

```text ServiceRequest = laboratory order
Task          = workflow tracking
Observation   = result measurement
DiagnosticReport = diagnostic report
```

Determine whether Task is:

```text per requisition
per test
per ServiceRequest
```

and ensure the chosen model conforms to the R4 element cardinalities.

Do not design a single Task whose `focus` ambiguously represents multiple
ServiceRequests.

---

# 30. PROVENANCE READINESS

Before implementing Provenance, define the events worth representing.

Typical candidates:

```text requested
entered
collected
performed
validated
unvalidated
printed
cancelled
corrected
```

For each event determine:

```text target resource
event time
agent
agent role
recorded vs event time
reason
historical/reconstructed status
```

Do not fabricate contemporaneous provenance for pre-migration records.

---

# 31. AUDIT EVENT READINESS

Determine how application audit differs from clinical Provenance.

Conceptually:

```text Provenance = why/how a clinical resource changed and who participated
AuditEvent   = security/access/audit activity
```

Do not collapse the two merely because both use actor information.

If FHIR AuditEvent read access is implemented, prevent self-generating access
loops where reading audit records continuously creates more records in the same
logical result.

---

# 32. AUTHENTICATION AND API READINESS

Before exposing `/fhir/r4`, decide:

```text session vs token authentication
read-only vs write
resource permissions
search permissions
_include permissions
vread permissions
rate limiting
error model
```

Do not add API token infrastructure unless there is an actual consumer
requirement.

Read-only is the safe initial boundary where the LIS has no external write
consumer.

---

# 33. SECURITY EQUIVALENCE

FHIR access must not become a new route around existing authorization.

Prove equivalence across:

```text direct read
search
paging
_include
history/vread
```

A user unable to view a clinical result through the LIS must not discover it
through a FHIR search result.

Authorization applies to included resources as well as primary resources.

---

# 34. TIME AND DATE READINESS

Establish:

```text application timezone
database timestamp storage
display timezone
date-only semantics
dateTime semantics
offset behaviour
calendar system
```

FHIR `date` and `dateTime` must be selected from the actual source semantics.

Do not add a time component to a date that the domain never captured.

Do not fabricate an offset without establishing the application's time zone.

---

# 35. API CONTRACT READINESS

Before implementing REST endpoints, define:

```text endpoint
HTTP method
content type
authentication
authorization
FHIR interaction
success response
error response
paging
search parameters
```

Every declared CapabilityStatement interaction must correspond to an actual
implemented endpoint.

Every implemented endpoint must be represented accurately in the
CapabilityStatement.

---

# 36. FHIR VALIDATOR READINESS

Before making a conformance claim, establish a repeatable validation environment:

```text official HL7 validator
FHIR version 4.0.1
Java runtime requirement
validation command
fixture location
CI invocation
```

Required evidence:

```text fixture
    ↓
FHIR validator
    ↓
result
```

A code review alone is not conformance evidence.

Warnings must be enumerated and explained.

---

# 37. TEST READINESS

Before implementation, inventory existing tests for:

```text requisitions
results
validation
unvalidation
printing
permissions
actor snapshots
client-actor stripping
staff/account management
catalogue
```

Identify important paths with no test coverage.

FHIR work must not assume that an untested behaviour is stable.

---

# 38. PRECONDITION CLASSIFICATION

Every identified gap must be classified:

```text ADAPTER ONLY
```

FHIR can represent the current data without changing the domain.

```text SMALL DOMAIN CHANGE
```

A narrow additive change is required to represent the domain honestly.

```text SIGNIFICANT DOMAIN CHANGE
```

FHIR representation depends on a broader clinical/domain decision.

Example:

```text no patient identity
    → SIGNIFICANT DOMAIN CHANGE

missing collection actor
    → SMALL DOMAIN CHANGE

missing LOINC mapping
    → ADAPTER/TERMINOLOGY FOUNDATION depending on requirements

FHIR XML support
    → ADAPTER ONLY
```

The exact classification must be evidence-based.

---

# 39. REQUIRED READINESS REPORT

The implementation agent must produce:

```text
docs/fhir/READINESS-REPORT.md
```

with these sections:

```text
1. Executive Summary
2. Repository Baseline
3. Actual Clinical Workflow
4. Patient Identity
5. Specimen Model
6. Actor/Provenance Model
7. Catalogue and Panel Behaviour
8. Result Semantics
9. Correction/Revision Behaviour
10. Report/Printing Behaviour
11. Terminology State
12. Identifier State
13. Security/API State
14. Test Coverage
15. Gaps Blocking Honest FHIR
16. Production Behaviour That Must Not Change
17. Open Questions
18. Evidence Index
```

Every material claim must identify its evidence.

Unknown information must be written as:

```text
UNKNOWN — NOT ESTABLISHED FROM REPOSITORY
```

Do not infer missing facts.

---

# 40. READINESS DECISION GATE

FHIR implementation may proceed only after the owner/lead has explicitly settled
the clinically material decisions.

At minimum:

```text
[ ] Patient identity model
[ ] Patient identifier meaning
[ ] Specimen representation
[ ] Requester vs enterer semantics
[ ] External referral semantics
[ ] Actor snapshot requirements
[ ] Result release semantics
[ ] Revision/history requirement
[ ] Panel representation
[ ] ServiceRequest granularity
[ ] Task granularity
[ ] DiagnosticReport granularity
[ ] Reference-range behaviour
[ ] Terminology verification policy
[ ] Public identifier strategy
[ ] API authentication boundary
[ ] API read/write boundary
[ ] Security permission mapping
```

---

# 41. HARD STOP CONDITIONS

The agent must stop and ask for a decision when any of these are unresolved:

```text patient identity cannot be established
result release semantics are ambiguous
historical correction behaviour is unknowable
actor provenance cannot be trusted
resource granularity is materially ambiguous
standard terminology would require guessing
identifier ownership is ambiguous
security equivalence cannot be demonstrated
```

The agent must not solve such gaps by silently changing the clinical meaning.

---

# 42. NON-GOALS AT THE PRECONDITION STAGE

Do not implement:

```text FHIR resources
FHIR transformers
FHIR routes
FHIR search
FHIR CapabilityStatement
HL7 v2
DHIS2
analyser interfaces
EMR integration
billing integration
notification integration
message brokers
```

until the readiness gate is complete.

---

# 43. CORE PRINCIPLE

The precondition test is simple:

> Can the current LIS tell the truth about what happened?

Only after that question has a satisfactory, evidence-backed answer should the
next question be asked:

> How should that truth be represented in FHIR R4?

FHIR is the interoperability layer.

The LIS remains the clinical workflow source of truth.
