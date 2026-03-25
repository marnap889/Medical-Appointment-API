# Domain model draft (MVP)

Aligned milestone:
- `M1 – Identity and authentication baseline`

## Core actors

### Patient
- can register an account with login credentials
- an account is active immediately after registration
- can log in and receive the bearer token
- can browse doctors by specialization and book appointments

### Doctor
- can register an account with login credentials
- specialization is required and should come from `DoctorSpecialization` enum
- can log in and receive bearer token with doctor role
- can define own availability and view own booked appointments

### DoctorSpecialization (enum, MVP starter set)
- values use PascalCase everywhere in the domain, API payloads, persistence mappings, and documentation
- `GeneralPractice`
- `Cardiology`
- `Dermatology`
- `Pediatrics`
- `Orthopedics`

## Core concepts

### PatientAccount
- stores patient personal data and authentication credentials
- patient email must be unique
- patient role is always `ROLE_PATIENT`
- patient cannot carry a doctor specialization

### DoctorAccount
- stores doctor identity, specialization, and authentication credentials
- doctor email must be unique
- doctor role is always `ROLE_DOCTOR`
- doctor specialization is mandatory at creation time

### AvailabilitySlot
- it belongs to exactly one doctor
- represents a bookable time window in that doctor's calendar

### Appointment
- links one patient, one doctor, and one slot
- lifecycle in MVP:
  - scheduled
  - cancelled

## Access context rules

- no global calendar view across all doctors and all patients
- calendar operations are always scoped to one doctor or one patient
- patient can have appointments with multiple doctors
- doctor can access only their own calendar and own appointment list
- backend authorization reloads the authenticated account and effective roles from the database by using the verified JWT `sub` only as a lookup key
- JWT roles claims are informational only and are not trusted for backend security assertions

## Invariants and MVP validation rules

- registration requires a valid email and password
- doctor registration additionally requires a supported specialization value
- the patient account is active by default after registration
- the doctor account is active by default after registration
- exactly one role is assigned to each account
- only hashed passwords are stored after registration succeeds
- slot end must be later than slot start
- the same slot cannot be booked twice for the same doctor
- a canceled appointment cannot be canceled again

## Registration process

### Patient registration

1. HTTP accepts a public registration payload with `email` and `password`.
2. Request validation rejects malformed JSON as `400` and semantic validation failures as `422`.
3. The application hashes the plain-text password before creating the domain account.
4. The domain creates a patient account with:
   - a generated identifier
   - normalized email
   - `ROLE_PATIENT`
   - active status set to `true`
   - no specialization
5. Persistence enforces email uniqueness.
6. The HTTP success response returns only the created account identifier.
7. The success payload shape is exactly `{ "id": "<uuid>" }`.

### Doctor registration

1. HTTP accepts a public registration payload with `email`, `password`, and `specialization`.
2. Request validation rejects malformed JSON as `400` and semantic validation failures as `422`.
3. The application hashes the plain-text password before creating the domain account.
4. The domain creates a doctor account with:
   - a generated identifier
   - normalized email
   - `ROLE_DOCTOR`
   - active status set to `true`
   - a required specialization from `DoctorSpecialization`
5. Persistence enforces email uniqueness.
6. The HTTP success response returns only the created account identifier.
7. The success payload shape is exactly `{ "id": "<uuid>" }`.

## Registration and exception behavior

- registration validation errors stay explicit and return `422 Unprocessable Entity`
- malformed request bodies return `400 Bad Request`
- duplicate account creation attempts return `409 Conflict`
- the conflict response is intentionally neutral and must not confirm whether a specific email already exists
- login failures remain neutral and return `401 Unauthorized` without revealing whether the email or password was wrong
- invalid or unverifiable bearer tokens return a bare `401 Unauthorized`
- unexpected exceptions collapse to a generic `500 Internal Server Error` response to avoid leaking internals

## MVP use-case flow (simple)

1. Patient registers -> account is active.
2. Patient logs in -> receives bearer token.
3. Patient queries doctors by specialization -> selects one doctor.
4. Patient books one available slot in the selected doctor's calendar.
5. Doctor manages their own availability and sees their own booked visits.
