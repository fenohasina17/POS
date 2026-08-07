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

# ── Serveur central ───────────────────────────────────────────
central-up:
	docker compose -f central/docker-compose.yml up -d

central-down:
	docker compose -f central/docker-compose.yml down

central-build:
	docker compose -f central/docker-compose.yml build --no-cache

central-logs:
	docker compose -f central/docker-compose.yml logs -f

central-migrate:
	docker compose -f central/docker-compose.yml exec central_backend php artisan migrate --force

# ── Git hooks ─────────────────────────────────────────────────
setup-hooks:
	@git config core.hooksPath .githooks
	@chmod +x .githooks/pre-commit
	@echo "[hooks] Pre-commit hook activé"
