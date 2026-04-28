FROM nginx:alpine

# Copie de la configuration directement dans l'image
COPY backend.conf /etc/nginx/conf.d/default.conf

# Création du dossier racine pour éviter les erreurs Nginx
RUN mkdir -p /var/www/public

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
