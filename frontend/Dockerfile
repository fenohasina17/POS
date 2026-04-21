# Stage build avec Node 20 compatible Vite
FROM node:20.19-bullseye-slim AS build
WORKDIR /app
ENV PATH /app/node_modules/.bin:$PATH

RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential python3 ca-certificates && rm -rf /var/lib/apt/lists/*

COPY package*.json ./

# Installer toutes les dépendances (dev incluses)
RUN npm install --no-audit --prefer-offline --include=dev

COPY . .
RUN npm run build

# Stage production: servir avec nginx
FROM nginx:stable-alpine AS prod
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
