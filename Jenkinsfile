pipeline {
    agent any
    
    stages {
        stage('📥 Récupération du Code') {
            steps {
                // Jenkins récupère le code de ton repo GitHub
                git branch: 'main', url: 'https://github.com/giov2002/Deployement-Application-web.git'
            }
        }

        stage('📦 Build des Images') {
            steps {
                echo 'Construction des images locales pour Global Purchase...'
                // On build avec des noms d'images locaux
                sh "docker build -t global-purchase-back ./backend"
                sh "docker build -t global-purchase-front ./frontend"
            }
        }

        stage('🧪 Tests Automatisés') {
            steps {
                echo 'Lancement des tests Laravel...'
                // On peut lancer les tests à l'intérieur du conteneur qu'on vient de builder
                sh "docker run --rm global-purchase-back ./vendor/bin/phpunit"
            }
        }

        stage('🚀 Mise en Production Locale') {
            steps {
                echo 'Déploiement des nouveaux conteneurs...'
                // Ici on utilise ton docker-compose pour rafraîchir l'app
                sh "docker-compose up -d --build"
            }
        }
    }
}