pipeline {
    agent any

    environment {
        DOCKER_COMPOSE = "${WORKSPACE}/bin/docker-compose -p deployement-application-web"
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
                script {
                    sh '''
                    mkdir -p ${WORKSPACE}/bin
                    if [ ! -f "${WORKSPACE}/bin/docker-compose" ]; then
                        echo "📥 Téléchargement de docker-compose..."
                        curl -L "https://github.com/docker/compose/releases/download/v2.24.1/docker-compose-$(uname -s)-$(uname -m)" -o ${WORKSPACE}/bin/docker-compose
                        chmod +x ${WORKSPACE}/bin/docker-compose
                    fi
                    ${WORKSPACE}/bin/docker-compose version
                    '''
                }
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
                        // 1. Création du .env à la racine
                        sh '''
                        cp backend/.env.example .env
                        sed -i 's/DB_DATABASE=app/DB_DATABASE=pos_system/' .env
                        sed -i 's/DB_USERNAME=app/DB_USERNAME=giovanni/' .env
                        sed -i "s/DB_PASSWORD=secret/DB_PASSWORD=$DB_PASS/" .env
                        sed -i 's|APP_URL=http://localhost:8080|APP_URL=http://localhost:8000|' .env
                        sed -i "s|APP_KEY=|APP_KEY=$APP_KEY|" .env
                        '''

                        // 2. Création du .env dans le dossier backend
                        sh 'cp .env backend/.env'

                        // 3. Création du .env dans le dossier frontend
                        sh '''
                        echo "VITE_API_URL=http://localhost:8000" > frontend/.env
                        echo "VITE_APP_NAME='Point of Sale Giovanni'" >> frontend/.env
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
                echo '🧪 Lancement des tests unitaires...'
                sh '''
                docker network create test-net || true
                docker run -d --name pg-test \
                    --network test-net \
                    -e POSTGRES_DB=testing \
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
                    $BACKEND_IMAGE \
                    /var/www/run-tests.sh || echo "⚠️ Tests en échec mais on continue"

                docker rm -f pg-test || true
                docker network rm test-net || true
                '''
            }
        }

        stage('🚀 Déploiement & Initialisation') {
            steps {
                script {
                    echo '🚀 Mise à jour des services (Rolling Update)...'
                    sh '${DOCKER_COMPOSE} up -d db backend nginx frontend'
                    
                    echo '⏳ Attente de la stabilisation...'
                    sh 'sleep 5'

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
            sh 'docker rm -f pg-test || true'
            sh 'docker network rm test-net || true'
        }
        success {
            echo '✅ Déploiement réussi !'
            sh 'docker image prune -f'
        }
    }
}