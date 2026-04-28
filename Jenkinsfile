pipeline {
    agent any

    options {
        timeout(time: 30, unit: 'MINUTES')
        ansiColor('xterm')
    }
    
    stages {
        stage('📥 Récupération du Code') {
            steps {
                // On s'assure d'être sur la bonne branche
                checkout scm
            }
        }

        stage('📦 Build des Images') {
            steps {
                echo '🏗️ Construction des images via Docker Compose...'
                sh 'docker-compose build --no-cache'
            }
        }

        stage('🧪 Tests Automatisés') {
            steps {
                echo '🧪 Lancement des tests unitaires et fonctionnels...'
                sh '''
                docker network create test-net || true
                docker run -d --name pg-test \
                    --network test-net \
                    -e POSTGRES_DB=testing \
                    -e POSTGRES_PASSWORD=password \
                    postgres:15-alpine

                sleep 10

                # On utilise l'image buildée par docker-compose (généralement nommée par le dossier)
                # On récupère le nom de l'image backend générée
                BACKEND_IMAGE=$(docker images --format "{{.Repository}}" | grep backend | head -n 1)

                docker run --rm \
                    --network test-net \
                    -e APP_ENV=testing \
                    -e APP_KEY=base64:KFOlFFNXabFku6rDUj51Y1cq47i+ivysqwsh1Pz6KOw= \
                    -e DB_CONNECTION=pgsql \
                    -e DB_HOST=pg-test \
                    -e DB_PORT=5432 \
                    -e DB_DATABASE=testing \
                    -e DB_USERNAME=postgres \
                    -e DB_PASSWORD=password \
                    $BACKEND_IMAGE \
                    bash -c "php artisan migrate --force && php vendor/bin/phpunit"

                docker rm -f pg-test
                docker network rm test-net
                '''
            }
        }

        stage('🚀 Déploiement & Initialisation') {
            steps {
                echo '🚀 Lancement de l\'application...'
                sh 'docker-compose up -d'
                
                echo '⏳ Attente du démarrage de la base de données...'
                sh 'sleep 15'

                echo '🔧 Exécution des migrations et du seeding...'
                sh 'docker-compose exec -T backend php artisan migrate:fresh --seed --force'
                
                echo '🔑 Optimisation de Laravel...'
                sh 'docker-compose exec -T backend php artisan config:cache'
                sh 'docker-compose exec -T backend php artisan route:cache'
            }
        }
    }

    post {
        always {
            echo '🧹 Nettoyage des ressources de test...'
            sh 'docker rm -f pg-test || true'
            sh 'docker network rm test-net || true'
        }
        success {
            echo '✅ Pipeline terminé avec succès ! L\'application est disponible sur http://localhost:8000'
            // Nettoyage des images orphelines pour économiser de l'espace
            sh 'docker image prune -f'
        }
        failure {
            echo '❌ Échec du pipeline. Vérifiez les logs ci-dessus.'
        }
    }
}