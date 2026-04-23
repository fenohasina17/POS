pipeline {
    agent any
    
    stages {
        stage('📥 Récupération du Code') {
            steps {
                git branch: 'main', url: 'https://github.com/giov2002/Deployement-Application-web.git'
            }
        }

        stage('📦 Build des Images') {
            steps {
                sh 'docker build -t global-purchase-back ./backend'
                sh 'docker build -t global-purchase-front ./frontend'
            }
        }

        stage('🧪 Tests Automatisés') {
            steps {
                echo 'Lancement des tests avec password123...'
                sh '''
                docker run --rm \
                    --network deployement-application-web_pos_network \
                    -e APP_KEY=base64:$(openssl rand -base64 32) \
                    -e DB_CONNECTION=pgsql \
                    -e DB_HOST=db \
                    -e DB_PORT=5432 \
                    -e DB_DATABASE=global_purchase \
                    -e DB_USERNAME=postgres \
                    -e DB_PASSWORD=password123 \
                    global-purchase-back \
                    php vendor/bin/phpunit tests
                '''
            }
        }

        stage('🚀 Mise en Production Locale') {
            steps {
                sh 'docker-compose up -d --build'
            }
        }
    }
}