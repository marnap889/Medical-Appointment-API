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

Current runtime baseline is intentionally minimal (`GET /api/health`) so AI workflows can grow the codebase in controlled increments.

## Why modular monolith

For a medical appointment API, a modular monolith is the best trade-off:
- simpler to review than microservices
- enough room to show boundaries and architecture
- easier to test locally
- less incidental complexity

## Persistence strategy

The bootstrap starts with an in-memory repository for the first use case so that:
- domain rules can be implemented and tested immediately
- Doctrine mapping can be introduced incrementally
- AI agents can evolve the system in small, auditable steps

## Logging and auditability

Two layers exist:
- application runtime logs
- AI workflow evidence logs

The second layer is intentionally isolated under `ai-orchestration/runtime/` to keep AI workflow artifacts separate from Symfony runtime/application code.
