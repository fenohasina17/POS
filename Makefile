.PHONY: \
  pos-up pos-down pos-restart pos-build pos-logs pos-ps pos-migrate pos-env pos-sync \
  central-up central-down central-build central-logs central-migrate \
  setup-hooks

# ── Caisse POS ────────────────────────────────────────────────
pos-env:
	@cd pos && bash generate-env.sh

pos-up: pos-env
	docker compose -f pos/docker-compose.yml up -d

pos-down:
	docker compose -f pos/docker-compose.yml down

pos-restart: pos-down pos-up

pos-build: pos-env
	docker compose -f pos/docker-compose.yml build --no-cache

pos-logs:
	docker compose -f pos/docker-compose.yml logs -f

pos-ps:
	docker compose -f pos/docker-compose.yml ps

pos-migrate:
	docker compose -f pos/docker-compose.yml exec backend php artisan migrate --force

pos-sync:
	docker compose -f pos/docker-compose.yml exec backend php artisan pos:sync

# ── Serveur central (dev local — ports publiés individuellement, pas de TLS) ──
CENTRAL_COMPOSE := -f central/docker-compose.yml -f central/docker-compose.dev.yml

central-up:
	docker compose $(CENTRAL_COMPOSE) up -d

central-down:
	docker compose $(CENTRAL_COMPOSE) down

central-build:
	docker compose $(CENTRAL_COMPOSE) build --no-cache

central-logs:
	docker compose $(CENTRAL_COMPOSE) logs -f

central-migrate:
	docker compose $(CENTRAL_COMPOSE) exec central_backend php artisan migrate --force

# ── Git hooks ─────────────────────────────────────────────────
setup-hooks:
	@git config core.hooksPath .githooks
	@chmod +x .githooks/pre-commit
	@echo "[hooks] Pre-commit hook activé"
