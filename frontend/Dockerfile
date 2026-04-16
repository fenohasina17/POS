# ── Stage 1 : build Vue.js ────────────────────────────────────────────────
FROM node:20-alpine AS builder

WORKDIR /app

COPY package*.json ./
RUN npm ci --prefer-offline

COPY . .
RUN npm run build

# ── Stage 2 : serveur Nginx ───────────────────────────────────────────────
FROM nginx:1.27-alpine AS final

# Config Nginx pour SPA Vue.js
COPY --from=builder /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]