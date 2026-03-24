SHELL := /bin/bash
COMPOSE := docker compose -f compose.yaml -f compose.dev.yaml
COMPOSE_PROD := docker compose -f compose.yaml

.PHONY: test test-unit test-behat phpstan cs cs-fix qa up down up-prod down-prod composer-install composer-update ai-bootstrap ai-workflow ai-parallel ai-evidence

up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

up-prod:
	$(COMPOSE_PROD) up -d --build

down-prod:
	$(COMPOSE_PROD) down

composer-install:
	$(COMPOSE) run --rm --no-deps php composer install --no-interaction

composer-update:
	$(COMPOSE) run --rm --no-deps php composer update --no-interaction

test: test-unit test-behat

test-unit:
	$(COMPOSE) exec php vendor/bin/phpunit

test-behat:
	$(COMPOSE) exec php vendor/bin/behat -c behat.yml.dist

phpstan:
	$(COMPOSE) exec php vendor/bin/phpstan analyse

cs:
	$(COMPOSE) exec php vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	$(COMPOSE) exec php vendor/bin/php-cs-fixer fix

qa: test phpstan cs

ai-bootstrap:
	./ai-orchestration/scripts/ai_bootstrap_runtime.sh

ai-workflow:
	@test -n "$(ROLE)" || (echo "Set ROLE=<architecture|implementation|review|testing|security|synthesis>" && exit 1)
	@test -n "$(TASK)" || (echo "Set TASK='<task description>'" && exit 1)
	./ai-orchestration/scripts/ai_run_workflow.sh "$(ROLE)" "$(TASK)"

ai-parallel:
	./ai-orchestration/scripts/ai_run_parallel_workflow.sh "$(or $(TASK),Review the booking module and propose next steps)"

ai-evidence:
	./ai-orchestration/scripts/ai_package_evidence.sh
