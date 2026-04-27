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
                echo 'Construction des images...'
                sh 'docker build -t global-purchase-back ./backend'
                sh 'docker build -t global-purchase-front ./frontend'
            }
        }

        stage('🧪 Tests Automatisés') {
            steps {
                echo 'Exécution des tests (Correction Table Cache)...'
                sh '''
                docker run --rm \
                    -e APP_ENV=testing \
                    -e APP_KEY=base64:$(openssl rand -base64 32) \
                    -e DB_CONNECTION=sqlite \
                    -e DB_DATABASE=:memory: \
                    -e CACHE_DRIVER=array \
                    -e SESSION_DRIVER=array \
                    -e QUEUE_CONNECTION=sync \
                    global-purchase-back \
                    bash -c "php artisan migrate:install && php artisan cache:table && php artisan migrate --force && php vendor/bin/phpunit tests"
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
        success {
            echo '✅ Pipeline terminé avec succès !'
        }
        failure {
            echo '❌ Le pipeline a échoué. Vérifie les logs des tests.'
        }
    }
}