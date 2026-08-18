#!/bin/sh
set -e

cat > /usr/share/nginx/html/env-config.js <<EOF
window.__ENV__ = {
  VITE_API_URL: "${API_URL:-http://localhost:9000}",
  VITE_REVERB_APP_KEY: "${REVERB_APP_KEY:-}",
  VITE_REVERB_HOST: "${REVERB_HOST:-localhost}",
  VITE_REVERB_PORT: "${REVERB_PORT:-9000}",
  VITE_REVERB_SCHEME: "${REVERB_SCHEME:-ws}"
};
EOF
