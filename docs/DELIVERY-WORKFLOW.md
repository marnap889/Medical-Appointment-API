# Delivery workflow

## Local development flow

1. Start containers with dev override (`compose.yaml` + `compose.dev.yaml`)
2. Install dependencies
3. Implement small use case
4. Run PHPUnit, Behat, PHPStan and CS Fixer
5. Update OpenAPI and docs
6. Run AI review/security/testing passes via `ai-orchestration/scripts/`
7. Package evidence from `ai-orchestration/runtime/evidence/`

## CI recommendation

Current CI should include:
- AI orchestration smoke workflow (`.github/workflows/ai-orchestration-smoke.yml`)
- composer validate
- phpunit
- behat
- phpstan
- php-cs-fixer --dry-run
- dependency audit
- OpenAPI validation
