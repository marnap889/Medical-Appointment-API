# Delivery workflow

## Local development flow

1. Start containers with dev override (`compose.yaml` + `compose.dev.yaml`)
2. Install dependencies
3. Select one active milestone from `docs/ROADMAP-MILESTONES.md`
4. Implement milestone-scoped use case
5. Run PHPUnit, Behat, PHPStan and CS Fixer
6. Update OpenAPI and docs
7. Run AI review/security/testing passes via `ai-orchestration/scripts/`
8. Package evidence from `ai-orchestration/runtime/evidence/`
9. Commit when milestone acceptance criteria are met

## CI recommendation

Current CI should include:
- AI workflow setup validation (`.github/workflows/ci.yml`)
- composer validate
- phpunit
- behat
- phpstan
- php-cs-fixer --dry-run
- dependency audit
- OpenAPI validation
