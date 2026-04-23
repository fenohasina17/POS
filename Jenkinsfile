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
                echo 'Construction des images locales...'
                // On build normalement, même si le key:generate échoue dans le Dockerfile, 
                // le "|| true" du développeur permet au build de continuer.
                sh "docker build -t global-purchase-back ./backend"
                sh "docker build -t global-purchase-front ./frontend"
            }
        }

        stage('🧪 Tests Automatisés') {
            steps {
                echo 'Préparation et lancement des tests sur PostgreSQL...'
                
                /* Ici on fait tout en une seule commande docker run :
                   1. On connecte au réseau de la DB
                   2. On passe toutes les variables d'environnement (-e) pour éviter le besoin du fichier .env
                   3. On force la configuration de PHPUnit
                */
                sh """
                docker run --rm \
                    --network deployement-application-web_pos_network \
                    -e APP_KEY=base64:$(openssl rand -base64 32) \
                    -e DB_CONNECTION=pgsql \
                    -e DB_HOST=db \
                    -e DB_PORT=5432 \
                    -e DB_DATABASE=global_purchase \
                    -e DB_USERNAME=postgres \
                    -e DB_PASSWORD=postgres \
                    global-purchase-back \
                    ./vendor/bin/phpunit --configuration phpunit.xml
                """
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