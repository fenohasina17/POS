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
                echo 'Construction des images Docker...'
                sh 'docker build -t global-purchase-back ./backend'
                sh 'docker build -t global-purchase-front ./frontend'
            }
        }

        stage('🧪 Tests Automatisés') {
            steps {
                echo 'Lancement des tests sur PostgreSQL (Sidecar)...'
                sh '''
                # 1. Créer un réseau temporaire pour que les conteneurs se voient
                docker network create test-net || true

                # 2. Lancer un PostgreSQL temporaire
                docker run -d --name pg-test \
                    --network test-net \
                    -e POSTGRES_DB=testing \
                    -e POSTGRES_PASSWORD=password \
                    postgres:15-alpine

                # Attendre que Postgres soit prêt
                sleep 10

                # 3. Lancer les tests en pointant sur ce PostgreSQL
                docker run --rm \
                    --network test-net \
                    -e APP_ENV=testing \
                    -e APP_KEY=base64:$(openssl rand -base64 32) \
                    -e DB_CONNECTION=pgsql \
                    -e DB_HOST=pg-test \
                    -e DB_PORT=5432 \
                    -e DB_DATABASE=testing \
                    -e DB_USERNAME=postgres \
                    -e DB_PASSWORD=password \
                    -e CACHE_DRIVER=array \
                    global-purchase-back \
                    bash -c "php artisan migrate --force && php vendor/bin/phpunit tests"

                # 4. Nettoyage : Supprimer le Postgres de test
                docker rm -f pg-test
                docker network rm test-net
                '''
            }
        }

        stage('🚀 Mise en Production Locale') {
            steps {
                echo 'Déploiement final...'
                sh 'docker-compose up -d --build'
            }
        }
    }

    post {
        always {
            // Sécurité pour ne pas laisser de conteneurs traîner en cas d'échec
            sh 'docker rm -f pg-test || true'
            sh 'docker network rm test-net || true'
        }
        success {
            echo '✅ Pipeline réussi avec PostgreSQL !'
        }
        failure {
            echo '❌ Le pipeline a échoué.'
        }
    }
}