.PHONY: up down restart logs build ps migrate setup-hooks

up: generate-env
	docker compose up -d

setup-hooks:
	@git config core.hooksPath .githooks
	@chmod +x .githooks/pre-commit
	@echo "[hooks] Pre-commit hook activé (protection anti-secrets)"

down:
	docker compose down

restart: down up

build: generate-env
	docker compose build --no-cache

logs:
	docker compose logs -f

ps:
	docker compose ps

migrate:
	docker compose exec backend php artisan migrate --force

generate-env:
	@bash generate-env.sh
