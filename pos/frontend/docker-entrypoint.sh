#!/bin/sh
# Génère /usr/share/nginx/html/env-config.js au démarrage du container.
# Les variables sont lues depuis l'environnement Docker (pas baked au build).
cat > /usr/share/nginx/html/env-config.js <<EOF
window.__ENV__ = {
  VITE_API_URL: "${API_URL:-http://localhost:8000}",
  VITE_REVERB_APP_KEY: "${REVERB_APP_KEY:-}",
  VITE_REVERB_HOST: "${REVERB_HOST:-localhost}",
  VITE_REVERB_PORT: "${REVERB_PORT:-8000}",
  VITE_REVERB_SCHEME: "${REVERB_SCHEME:-ws}"
};
EOF
