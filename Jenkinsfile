pipeline {
    agent any

    options {
        timeout(time: 30, unit: 'MINUTES')
    }
    
    stages {
        stage('📥 Récupération du Code') {
            steps {
                git branch: 'main', url: 'https://github.com/giov2002/Deployement-Application-web.git'
            }
        }

        stage('📦 Build des Images') {
            steps {
                echo 'Construction des images locales pour Global Purchase...'
                sh "docker build -t global-purchase-back ./backend"
                sh "docker build -t global-purchase-front ./frontend"
            }
        }

        stage('🧪 Tests Automatisés') {
            steps {
                echo 'Lancement des tests Laravel...'
                // CORRECTION : On crée le fichier sqlite vide AVANT de lancer les tests
                // On utilise "touch" à l'intérieur du conteneur
                sh "docker run --rm global-purchase-back sh -c 'touch database/database.sqlite && ./vendor/bin/phpunit'"
            }
        }

        stage('🚀 Mise en Production Locale') {
            steps {
                echo 'Déploiement des nouveaux conteneurs...'
                sh "docker-compose up -d --build"
            }
        }
    }
}