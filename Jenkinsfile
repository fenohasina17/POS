pipeline {
    agent any

    environment {
        DOCKER_COMPOSE = "docker compose -p deployement-application-web"
    }

    options {
        timeout(time: 30, unit: 'MINUTES')
        ansiColor('xterm')
    }

    stages {
        stage('🎬 Début') {
            steps {
                echo "🚀 Démarrage du pipeline pour le projet: deployement-application-web"
            }
        }

        stage('🛠️ Préparation des outils') {
            steps {
                sh 'docker compose version'
            }
        }

        stage('📥 Récupération du Code') {
            steps {
                checkout scm
                script {
                    echo "🔧 Création des fichiers d'environnement..."

                    withCredentials([
                        string(credentialsId: 'db-password', variable: 'DB_PASS'),
                        string(credentialsId: 'app-key', variable: 'APP_KEY')
                    ]) {
                        // Génération directe du .env racine (pas de dépendance à .env.example)
                        sh '''
                        cat > .env <<EOF
APP_NAME=POS
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=pos_system
DB_USERNAME=giovanni
DB_PASSWORD=${DB_PASS}
CACHE_STORE=redis
SESSION_DRIVER=file
SESSION_LIFETIME=120
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
LOG_LEVEL=error
EOF
                        '''

                        // Copie dans le dossier backend
                        sh 'cp .env backend/.env'

                        // .env frontend
                        sh '''
                        printf "VITE_API_URL=http://localhost:8000/api\nVITE_APP_NAME=Point of Sale\n" > frontend/.env
                        '''
                    }

                    echo "✅ Fichiers .env préparés."
                }
            }
        }

        stage('📦 Build des Images') {
            steps {
                echo '🏗️ Construction des images...'
                sh '${DOCKER_COMPOSE} build'
            }
        }

        stage('🧪 Tests Automatisés') {
            steps {
                catchError(buildResult: 'UNSTABLE', stageResult: 'UNSTABLE') {
                    echo '🧪 Lancement des tests unitaires...'
                    sh '''
                    docker network create test-net || true
                    docker run -d --name pg-test \
                        --network test-net \
                        -e POSTGRES_DB=testing \
                        -e POSTGRES_USER=postgres \
                        -e POSTGRES_PASSWORD=password \
                        postgres:15-alpine

                    until docker exec pg-test pg_isready -U postgres; do
                        echo "⏳ Attente de PostgreSQL..."
                        sleep 2
                    done

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
                        -e CACHE_STORE=array \
                        -e SESSION_DRIVER=array \
                        -e QUEUE_CONNECTION=sync \
                        "$BACKEND_IMAGE" \
                        /var/www/run-tests.sh
                    '''
                }
            }
        }

        stage('🚀 Déploiement & Initialisation') {
            steps {
                script {
                    echo '🚀 Mise à jour des services (Rolling Update)...'
                    sh '${DOCKER_COMPOSE} down --remove-orphans || true'
                    sh 'docker ps -q --filter publish=8000 | xargs -r docker stop || true'
                    sh '${DOCKER_COMPOSE} up -d db redis backend nginx frontend'

                    echo '⏳ Attente de la stabilisation (20s)...'
                    sh 'sleep 20'

                    echo '🔧 Migration de la base de données...'
                    sh '${DOCKER_COMPOSE} exec -T backend php artisan migrate --force'

                    echo '🔑 Nettoyage du cache...'
                    sh '${DOCKER_COMPOSE} exec -T backend php artisan config:clear'
                }
            }
        }
    }

    post {
        always {
            script {
                sh 'docker rm -f pg-test 2>/dev/null || true'
                sh 'docker network rm test-net 2>/dev/null || true'
            }
        }
        success {
            echo '✅ Déploiement réussi !'
            sh 'docker image prune -f'
        }
        failure {
            echo '❌ Le pipeline a échoué. Vérifier les logs ci-dessus.'
        }
    }
}
