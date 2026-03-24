# MVP milestone roadmap

This file is the persistent implementation plan for AI agents and humans.
Treat it as the default source of truth for delivery order.

## Working rule

- work on exactly one milestone at a time
- if a task request does not specify a milestone, pick the next incomplete milestone
- keep scope inside the selected milestone (defer extras)
- when milestone acceptance criteria are met, finish with one repository commit

## Milestone status

- [ ] M1 – Identity and authentication baseline
- [ ] M2 – Doctor directory and specialization browsing
- [ ] M3 – Doctor availability management
- [ ] M4 – Appointment booking by patient
- [ ] M5 – Appointment views and cancellation
- [ ] M6 – MVP hardening and contract closure

## M1 – Identity and authentication baseline

Scope:
- patient registration with credentials
- patient login with bearer token
- doctor registration with credentials and required specialization
- doctor login with bearer token and doctor role
- patient email unique constraint
- accounts active by default after registration
- password hash storage only
- Doctrine migrations for added schema

Acceptance criteria:
- registration and login exist for patient and doctor
- authentication returns the bearer token for valid credentials
- role split is enforced (`ROLE_PATIENT`, `ROLE_DOCTOR`)
- DB schema and migrations are committed

## M2 – Doctor directory and specialization browsing

Scope:
- specialization dictionary endpoint
- doctors listing endpoint filtered by specialization
- minimal doctor profile fields for listing

Acceptance criteria:
- patient can query doctors by specialization
- specialization values come from controlled dictionary

## M3 – Doctor availability management

Scope:
- doctor creates, lists, and removes their own availability slots
- slot validation (`start < end`)
- conflict prevention for overlapping/duplicate slots per doctor

Acceptance criteria:
- doctor can manage only their own availability
- no global multi-doctor calendar exposure

## M4 – Appointment booking by patient

Scope:
- patient books an available slot in a selected doctor's calendar
- appointment created with `scheduled` status
- double-booking prevention
- Doctrine migrations for booking schema updates

Acceptance criteria:
- patient can book one free slot
- the same slot cannot be booked twice

## M5 – Appointment views and cancellation

Scope:
- patient sees own appointments (across many doctors)
- doctor sees their own booked appointments
- patient can cancel their own appointment
- cancellation status transition to `cancelled`

Acceptance criteria:
- read access is strictly scoped to one patient or one doctor
- a canceled appointment cannot be canceled again

## M6 – MVP hardening and contract closure

Scope:
- OpenAPI completion for all MVP endpoints
- consistent auth/validation error responses
- final docs alignment (`docs/`)
- final review of privacy/security constraints

Acceptance criteria:
- OpenAPI reflects implemented MVP behavior
- docs and implementation are aligned
