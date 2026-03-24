# Architecture overview

## Style

This repository follows a **modular monolith** with **Clean Architecture / Hexagonal** tendencies:

- Domain
- Application
- Infrastructure
- UI/HTTP

The design goal is to keep the core booking logic isolated from transport and storage concerns.

## Bounded capabilities (target state)

- Patient management
- Doctor directory
- Schedule availability
- Appointment booking
- Appointment cancellation
- Authentication (patient and doctor login with bearer token)

Current runtime baseline is intentionally minimal (`GET /api/health`) so AI workflows can grow the codebase in controlled increments.

## Why modular monolith

For a medical appointment API, a modular monolith is the best trade-off:
- simpler to review than microservices
- enough room to show boundaries and architecture
- easier to test locally
- less incidental complexity

## Persistence strategy

Runtime persistence is Doctrine-first:
- runtime repositories should use Doctrine adapters
- schema changes should include explicit Doctrine migrations in the same change set
- in-memory repositories are allowed only in tests

## Authorization and data scope

- both patients and doctors authenticate with credentials and bearer tokens
- role-based access is required (`ROLE_PATIENT`, `ROLE_DOCTOR`)
- doctor calendar endpoints are scoped to one doctor context
- patient appointment endpoints are scoped to one patient context
- do not expose cross-doctor/cross-patient global calendar data

## Logging and auditability

Two layers exist:
- application runtime logs
- AI workflow evidence logs

The second layer is intentionally isolated under `ai-orchestration/runtime/` to keep AI workflow artifacts separate from Symfony runtime/application code.
