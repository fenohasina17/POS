FROM nginx:alpine

RUN apk add --no-cache openssl

COPY backend.conf /etc/nginx/conf.d/default.conf
COPY generate-cert.sh /docker-entrypoint.d/10-generate-cert.sh
RUN chmod +x /docker-entrypoint.d/10-generate-cert.sh

RUN mkdir -p /var/www/public /etc/nginx/ssl

EXPOSE 80 443
CMD ["nginx", "-g", "daemon off;"]
