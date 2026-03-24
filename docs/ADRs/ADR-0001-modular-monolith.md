# ADR-0001: Use a modular monolith for the medical appointment API (MVP)

## Status
Accepted

## Context
The task must demonstrate strong engineering judgment, domain modeling and AI-first development workflow, while remaining reviewable in a small repository.

## Decision
Use a Symfony modular monolith with explicit Domain/Application/Infrastructure/UI separation.

## Consequences
### Positive
- simpler local development
- easier review
- lower ops complexity
- it still demonstrates architectural maturity

### Negative
- fewer cross-service concerns demonstrated
- future extraction paths must be designed consciously
