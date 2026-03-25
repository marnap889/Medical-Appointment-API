# Domain model draft (MVP)

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
- `GeneralPractice`
- `Cardiology`
- `Dermatology`
- `Pediatrics`
- `Orthopedics`

## Core concepts

### PatientAccount
- stores patient personal data and authentication credentials
- patient email must be unique

### DoctorAccount
- stores doctor identity, specialization, and authentication credentials
- doctor email must be unique

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

## Invariants and MVP validation rules

- registration requires a valid email and password
- the patient account is active by default after registration
- slot end must be later than slot start
- the same slot cannot be booked twice for the same doctor
- a canceled appointment cannot be canceled again

## MVP use-case flow (simple)

1. Patient registers -> account is active.
2. Patient logs in -> receives bearer token.
3. Patient queries doctors by specialization -> selects one doctor.
4. Patient books one available slot in the selected doctor's calendar.
5. Doctor manages their own availability and sees their own booked visits.
