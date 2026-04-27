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
                echo 'Lancement des tests unitaires et fonctionnels...'
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
                    bash -c "php artisan migrate --force && php vendor/bin/phpunit tests"
                '''
            }
        }

        stage('🚀 Mise en Production Locale') {
            steps {
                echo 'Déploiement de l’application avec Docker Compose...'
                sh 'docker-compose up -d --build'
            }
        }
    }

    post {
        success {
            echo '✅ Félicitations ! Le pipeline est passé et l’application est déployée.'
        }
        failure {
            echo '❌ Échec du pipeline. Vérifie les logs de l’étape en erreur.'
        }
    }
}