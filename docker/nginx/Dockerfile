FROM nginx:alpine

COPY backend.conf /etc/nginx/conf.d/default.conf
RUN mkdir -p /var/www/public

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
