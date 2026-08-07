# Stage 1 : build de l'application Vue.js
# Les variables VITE_* ne sont plus passées au build — elles sont injectées
# à runtime via /env-config.js généré au démarrage du container nginx.
FROM node:20-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2 : servir avec Nginx + injection des env vars au démarrage
FROM nginx:alpine
COPY --from=builder /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY docker-entrypoint.sh /docker-entrypoint.d/40-inject-env.sh
RUN chmod +x /docker-entrypoint.d/40-inject-env.sh

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
