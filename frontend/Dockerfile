FROM node:20-alpine

# On définit le dossier où sera stocké le code dans le conteneur
WORKDIR /app

# On copie d'abord les fichiers de dépendances
COPY package*.json ./

# On installe les outils nécessaires (npm)
RUN npm install

# On copie tout le reste du code source
COPY . .

# On expose le port 5173 utilisé par Vite
EXPOSE 5173

# On lance le serveur de développement avec l'option --host 
# pour que le conteneur accepte les connexions venant de ton PC
CMD ["npm", "run", "dev", "--", "--host"]