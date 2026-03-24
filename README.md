# AI-First Symfony Skeleton

AI-first repository for a domain-driven medical appointment API built with **PHP 8.5**, **Symfony 7.4**, **PostgreSQL**, **Docker**, **Codex CLI**, and the **OpenAI Agents SDK**.

## What this repository demonstrates

- a minimal Symfony API baseline (`/api/health`) intentionally kept small
- an AI-driven workflow that can iteratively build domain capabilities:
  - patient registration
  - doctor directory
  - schedule management
  - appointment booking
  - appointment cancellation
- clean architecture / modular monolith boundaries
- containerized local development
- unit and behavioral testing foundations
- OpenAPI-first documentation workflow
- security, privacy and compliance thinking:
  - OWASP
  - GDPR
  - ISO-minded auditability
  - WCAG-aware API documentation and operational process
- AI-first engineering workflow:
  - repository-level Codex guidance
  - project-scoped Codex configuration
  - multi-agent orchestration
  - reproducible local evidence pack generation
  - structured logs copied into this repo for review

## Repository structure

- `src/` – Symfony application code
- `tests/` – PHPUnit + Behat skeletons
- `docs/` – architecture, ADRs, security, privacy, API design
- `openapi/` – OpenAPI contract draft
- `ai-orchestration/` – isolated Agents SDK orchestration + runtime artifacts
- `scripts/` – compatibility wrappers delegating to `ai-orchestration/scripts/`
- `docker/` – local development containers

## Quick start

### 1. Build containers

```bash
# Dev profile (volume mount + Xdebug + composer in image)
docker compose -f compose.yaml -f compose.dev.yaml up -d --build

# Or shortcut:
make up
```

### 2. Install PHP dependencies inside the app container

```bash
docker compose -f compose.yaml -f compose.dev.yaml exec php composer install
```

### 3. Prepare database

```bash
docker compose -f compose.yaml -f compose.dev.yaml exec php php bin/console doctrine:database:create --if-not-exists
docker compose -f compose.yaml -f compose.dev.yaml exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### 4. Run quality checks

```bash
make test
make test-unit
make test-behat
make phpstan
make cs

# Unit coverage with Xdebug (dev profile)
docker compose -f compose.yaml -f compose.dev.yaml exec -e XDEBUG_MODE=coverage php vendor/bin/phpunit --testsuite Unit --coverage-text
```

### Production-like compose (without dev overrides)

```bash
docker compose -f compose.yaml up -d --build
# or
make up-prod
```

Notes:
- `php-prod` image includes app code + production dependencies and does not include Composer or Xdebug.
- `php-dev` image (from `compose.dev.yaml`) includes Composer + Xdebug and is intended for local development/tests.
- `.env.dist` is a plain template of required vars (`APP_ENV`, `APP_SECRET`, `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`, `POSTGRES_HOST`, `POSTGRES_PORT`, `POSTGRES_SERVER_VERSION`, `DATABASE_URL`).
- `DATABASE_URL` is built in `.env`/`.env.dist` from DB vars, so Doctrine always gets `DATABASE_URL` and DB values stay centralized.

### 5. Prepare AI orchestration

```bash
cd ai-orchestration
cp .env.dist .env
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

### 6. Run a multi-agent AI session

From the repository root:

```bash
./ai-orchestration/scripts/ai_bootstrap_runtime.sh
./ai-orchestration/scripts/ai_run_workflow.sh architecture "Design the next booking increment and ADR"
./ai-orchestration/scripts/ai_run_parallel_workflow.sh "Run architecture+implementation+review+testing+security and synthesize one plan"
```

Or via Make:

```bash
make ai-bootstrap
make ai-workflow ROLE=synthesis TASK="Merge specialist findings into one execution plan"
make ai-parallel TASK="Review current state and propose next increment"
```

Or via root wrappers:

```bash
./scripts/ai_bootstrap.sh
./scripts/ai_run_workflow.sh architecture "Design the next booking increment and ADR"
./scripts/ai_run_parallel_workflow.sh "Review current state and propose next increment"
```

### 7. Package evidence

```bash
./ai-orchestration/scripts/ai_package_evidence.sh
```

Artifacts will be copied into `ai-orchestration/runtime/evidence/export-<timestamp>/`.

## Current API baseline

- health endpoint: `GET /api/health`
- OpenAPI JSON: `GET /api/doc.json`
- Swagger UI: `GET /api/doc`

## Delivery philosophy

This repository intentionally separates:

- **product code** in Symfony/PHP
- **AI orchestration + AI artifacts** under `ai-orchestration/`
- **compatibility wrappers** in root `scripts/`

That separation makes the workflow easier to scale and audit.
