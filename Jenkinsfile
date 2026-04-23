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
                echo 'Exécution des tests sur SQLite (évite les erreurs de mot de passe Postgres)...'
                
                sh '''
                docker run --rm \
                    -e APP_KEY=base64:$(openssl rand -base64 32) \
                    -e DB_CONNECTION=sqlite \
                    -e DB_DATABASE=:memory: \
                    global-purchase-back \
                    php vendor/bin/phpunit tests
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
}