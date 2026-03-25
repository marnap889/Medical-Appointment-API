# Architecture overview

## Style

This repository follows a **modular monolith** with **Clean Architecture / Hexagonal** tendencies:

- Domain
- Application
- Infrastructure
- UI/HTTP

The design goal is to keep the core booking logic isolated from transport and storage concerns.

## Code organization policy

- keep code organized by layer and responsibility-specific subdirectories
- avoid flat layer roots with mixed artifact types
- use clear folder conventions (for example: `Entity/`, `Model/`, `Handler/`, `Dto/`)
- keep namespaces aligned with directory layout
- extend existing module structure consistently instead of introducing ad-hoc placement

## Bounded capabilities (target state)

- Patient management
- Doctor directory
- Schedule availability
- Appointment booking
- Appointment cancellation
- Authentication (patient and doctor login with bearer token)

Current runtime baseline includes `GET /api/health` plus M1 identity endpoints for patient registration, doctor registration, and login. The bootstrap strategy still applies to later milestones so capabilities continue to grow incrementally.

## Authentication architecture

The authentication flow stays split by responsibility:

- UI/HTTP parses JSON requests, validates the public DTO shape, and maps failures to problem responses
- Application handlers orchestrate registration and login use cases
- Domain keeps user identity, roles, and invariants free from transport details
- Infrastructure owns password hashing integration, JWT issuance/verification, and the bearer authenticator

This keeps controllers thin while preventing persistence or transport concerns from leaking into the core model.

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

- both patients and doctors authenticate with credentials and receive bearer JWTs
- the main Symfony firewall is stateless and uses a custom bearer token authenticator
- JWTs are issued with a one-hour TTL, the user identifier as subject, and role claims for downstream client context
- protected endpoints resolve the current user from the bearer token rather than from server-side session state
- backend authorization uses the verified JWT `sub` only as a lookup key, then reloads the current user and effective roles from the database
- JWT role claims are informational only for clients or downstream consumers and are not trusted for backend security assertions
- role-based access is required (`ROLE_PATIENT`, `ROLE_DOCTOR`)
- doctor calendar endpoints are scoped to one doctor context
- patient appointment endpoints are scoped to one patient context
- do not expose cross-doctor/cross-patient global calendar data

## Public response shaping

- registration endpoints return only the created account identifier (`{ "id": "<uuid>" }`)
- registration responses intentionally avoid echoing roles, activation flags, or specialization data that the client does not need immediately

## Enum conventions

- `DoctorSpecialization` values use PascalCase everywhere across the domain model, API contracts, persistence-facing mappings, and documentation
- examples include `GeneralPractice`, `Cardiology`, `Dermatology`, `Pediatrics`, and `Orthopedics`

## Validation and hardening boundaries

- password policy is enforced in HTTP request DTO validation before registration reaches the application layer
- invalid login attempts use one neutral unauthorized response regardless of whether the email or password was incorrect
- invalid or unverifiable bearer tokens fail with a bare `401 Unauthorized` response
- unexpected exceptions are collapsed to a generic `500` problem response to avoid leaking internals
- public API validation errors stay explicit (`400`/`422`) while authentication failures stay intentionally less descriptive

## Logging and auditability

Two layers exist:
- application runtime logs
- AI workflow evidence logs

The second layer is intentionally isolated under `ai-orchestration/runtime/` to keep AI workflow artifacts separate from Symfony runtime/application code.
