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
                sh 'docker build -t global-purchase-back ./backend'
                sh 'docker build -t global-purchase-front ./frontend'
            }
        }

        stage('🧪 Tests Automatisés') {
            steps {
                echo 'Lancement des tests (Injection des variables et exécution directe)...'
                
                /* On utilise les guillemets simples (''' ''') pour que Jenkins 
                   n'interprète pas le signe '$' de la commande openssl.
                */
                sh '''
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
                    php vendor/bin/phpunit tests
                '''
            }
        }

        stage('🚀 Mise en Production Locale') {
            steps {
                echo 'Déploiement des nouveaux conteneurs...'
                sh 'docker-compose up -d --build'
            }
        }
    }
}