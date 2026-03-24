# AGENTS.md

## Repository purpose

This repository is an AI-first project for the medical appointment API built with Symfony 7.4, PHP 8.5, PostgreSQL, Docker, PHPUnit, Behat and OpenAPI.

Current bootstrap state:
- the runtime application starts with a minimal health check endpoint
- AI workflows intentionally deliver domain capabilities incrementally

## Primary engineering goals

- Prefer clean, reviewable diffs over broad rewrites.
- Keep controllers thin.
- Keep business rules in domain/application layers, not in controllers or persistence adapters.
- Preserve clear boundaries between:
  - Domain
  - Application
  - Infrastructure
  - UI/HTTP
- Prefer KISS to speculative abstractions.
- Apply SOLID pragmatically, not dogmatically.
- Avoid accidental complexity.

## Domain expectations

The API covers:
- patient registration
- doctor browsing
- doctor schedule slots
- booking appointments
- cancelling appointments

Model the domain explicitly:
- identifiers as value objects where useful
- statuses as enums/value objects
- invariants enforced close to the domain model
- application services orchestrate use cases
- repositories are interfaces in the domain/application boundary

## PHP / Symfony conventions

- Use PHP 8.5 syntax where it improves readability.
- Follow Symfony conventions unless there is a strong reason not to.
- Prefer constructor injection.
- Keep validation explicit.
- Do not hide business rules inside Doctrine entities by accident.
- Avoid anemic "God services"; use focused handlers/services.
- Public API DTOs should be explicit.

## Security and compliance expectations

Always keep in mind:
- OWASP API security basics
- GDPR data minimization
- auditability of important business operations
- no secrets in repository
- avoid logging personal medical details unnecessarily
- prefer pseudonymous identifiers in logs when possible

## Testing expectations

Before marking work is complete:
- run PHPUnit unit tests when PHP code changes
- run Behat when behavior changes
- run PHPStan
- run PHP CS Fixer in dry-run mode
- mention uncovered edge cases and assumptions

For AI orchestration/script changes:
- run `bash -n` for changed shell scripts
- run `python3 -m py_compile` for changed Python orchestration files
- run `make -n ai-bootstrap ai-workflow ROLE=synthesis TASK="smoke" ai-parallel ai-evidence`
- verify runtime outputs stay under `ai-orchestration/runtime/`

## AI orchestration conventions

- Keep AI orchestration code and artifacts inside `ai-orchestration/`.
- Use `ai-orchestration/runtime/logs/` and `ai-orchestration/runtime/evidence/` only (no root-level logs/evidence folders).
- Use script names with `ai_` prefix for consistency.
- Specialist roles are: architecture, implementation, review, testing, security.
- Use the synthesis role to merge specialist outputs and resolve conflicts explicitly.
- Keep env templates as `.env.dist` and never commit real secrets.
- Default session mirroring should stay privacy-safe (`CODEX_SESSION_MIRROR_MODE=repo-only`) unless explicitly changed.

## Documentation expectations

When behavior or architecture changes:
- update relevant docs in `docs/`
- update `openapi/openapi.yaml` for public API changes
- update ADRs when a meaningful architectural decision is made
- update workflow docs if AI orchestration commands or runtime layout changed

## Output contract for AI work

Every completed task should include:
1. summary
2. files changed
3. risks
4. missing tests
5. assumptions
