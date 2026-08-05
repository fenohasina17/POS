.PHONY: up down restart logs build ps migrate

up: generate-env
	docker compose up -d

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
