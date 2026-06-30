# Stage 1 : build de l'application Vue.js
FROM node:20-alpine AS builder
WORKDIR /app
ARG VITE_API_URL
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME
ENV VITE_API_URL=$VITE_API_URL \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2 : servir avec Nginx + SSL
FROM nginx:alpine
RUN apk add --no-cache openssl
COPY --from=builder /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY generate-cert.sh /docker-entrypoint.d/10-generate-cert.sh
RUN chmod +x /docker-entrypoint.d/10-generate-cert.sh && mkdir -p /etc/nginx/ssl

EXPOSE 80 443
CMD ["nginx", "-g", "daemon off;"]
