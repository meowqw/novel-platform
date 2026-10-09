export DOCKER_BUILDKIT ?= 0
export COMPOSE_DOCKER_CLI_BUILD ?= 0

COMPOSE := docker compose -f compose.yaml -f compose.dev.yaml
COMPOSE_PROD := docker compose -f compose.yaml -f compose.prod.yaml
APP := $(COMPOSE) exec -T app

.PHONY: help up down build restart logs shell migrate fresh test pint stan quality health prod-build prod-up key

help: ## Show available targets
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-16s\033[0m %s\n", $$1, $$2}'

up: ## Start local stack
	$(COMPOSE) up -d --build

down: ## Stop local stack
	$(COMPOSE) down

build: ## Rebuild local images
	$(COMPOSE) build

restart: ## Restart local stack
	$(COMPOSE) restart

logs: ## Tail local logs
	$(COMPOSE) logs -f --tail=100

shell: ## Open shell in app container
	$(COMPOSE) exec app sh

key: ## Generate APP_KEY in .env
	$(APP) php artisan key:generate

migrate: ## Run migrations (explicit, never on container start)
	$(APP) php artisan migrate --force

fresh: ## migrate:fresh (destructive, local only)
	$(APP) php artisan migrate:fresh --force

test: ## Run Pest tests (isolated cache/queue; never shares app Redis throttle)
	$(COMPOSE) exec -T \
		-e DB_HOST=postgres \
		-e DB_DATABASE=novel_test \
		-e REDIS_HOST=redis \
		-e CACHE_STORE=array \
		-e QUEUE_CONNECTION=sync \
		-e SESSION_DRIVER=array \
		app php artisan test

pint: ## Run Pint in check mode
	$(APP) vendor/bin/pint --test

stan: ## Run PHPStan/Larastan
	$(APP) vendor/bin/phpstan analyse

quality: pint stan test ## Formatting, static analysis, tests

health: ## Hit local health endpoint
	curl -sf http://localhost:8080/api/v1/health && echo

prod-build: ## Build production images
	$(COMPOSE_PROD) build

prod-up: ## Start production compose (requires .env with production values)
	$(COMPOSE_PROD) up -d
