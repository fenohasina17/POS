FROM nginx:alpine

COPY backend.conf /etc/nginx/conf.d/default.conf

RUN mkdir -p /var/www/public /etc/nginx/ssl

EXPOSE 80 443
CMD ["nginx", "-g", "daemon off;"]
