FROM node:20

# Créer le dossier de l'application
WORKDIR /app

# Copier et installer dépendances
COPY package*.json ./
RUN npm install

# Copier le reste
COPY . .

EXPOSE 4200

CMD ["npm", "start"]
