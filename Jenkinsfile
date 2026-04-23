pipeline {
    agent any

    options {
        timeout(time: 30, unit: 'MINUTES')
    }
    
    stages {
        stage('📥 Récupération du Code') {
            steps {
                // On garde ta méthode de récupération qui marche bien
                git branch: 'main', url: 'https://github.com/giov2002/Deployement-Application-web.git'
            }
        }

        stage('📦 Build des Images') {
            steps {
                // On garde tes builds qui sont déjà en cache et rapides
                echo 'Construction des images...'
                sh 'docker build -t global-purchase-back ./backend'
                sh 'docker build -t global-purchase-front ./frontend'
            }
        }

        stage('🧪 Tests Automatisés') {
            steps {
                echo 'Exécution des tests (SQLite + Cache Array)...'
                
                // Correction : On force les drivers en "array" pour éviter l'erreur "no such table: cache"
                sh '''
                docker run --rm \
                    -e APP_KEY=base64:$(openssl rand -base64 32) \
                    -e DB_CONNECTION=sqlite \
                    -e DB_DATABASE=:memory: \
                    -e CACHE_DRIVER=array \
                    -e SESSION_DRIVER=array \
                    -e QUEUE_CONNECTION=sync \
                    global-purchase-back \
                    php vendor/bin/phpunit tests
                '''
            }
        }

        stage('🚀 Mise en Production Locale') {
            steps {
                // On garde ton déploiement final
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