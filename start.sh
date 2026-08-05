#!/usr/bin/env bash
# Wrapper docker compose : génère le .env si absent, puis lance les conteneurs
set -euo pipefail

bash "$(dirname "$0")/generate-env.sh"
docker compose up -d "$@"
