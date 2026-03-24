# Domain model draft

## Core concepts

### Patient
A patient can register and book appointments.

### Doctor
A doctor exposes specialization and schedule availability.

### AvailabilitySlot
A slot belongs to a doctor and represents a bookable time window.

### Appointment
An appointment links a patient and doctor to a chosen slot and has a lifecycle:
- scheduled
- cancelled

## Invariants
- slot end must be later than slot start
- the same slot cannot be booked twice for the same doctor
- a canceled appointment cannot be canceled again
