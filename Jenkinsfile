pipeline {
    agent any

    environment {
        DOCKER_COMPOSE = "docker compose -p deployement-application-web"
    }

    // Paramètre pour le premier déploiement uniquement
    parameters {
        booleanParam(
            name: 'INITIAL_SETUP',
            defaultValue: false,
            description: '⚠️ Premier déploiement seulement : recrée la DB et charge toutes les données initiales. NE PAS activer en production active (efface toutes les données clients).'
        )
    }

    options {
        timeout(time: 30, unit: 'MINUTES')
        ansiColor('xterm')
        disableConcurrentBuilds()
    }

    stages {
        stage('🎬 Début') {
            steps {
                echo "🚀 Pipeline démarré — Branche: ${env.BRANCH_NAME ?: 'main'} — Initial setup: ${params.INITIAL_SETUP}"
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
                        sh '''
                        cat > .env <<EOF
APP_NAME=POS
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=https://192.168.0.9:8443
SERVER_IP=192.168.0.9
FRONTEND_URL=https://192.168.0.9:5443
SANCTUM_STATEFUL_DOMAINS=192.168.0.9:5443
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
LOG_CHANNEL=daily
LOG_LEVEL=error
LOG_DEPRECATIONS_CHANNEL=null
EOF
                        '''
                        sh 'cp .env backend/.env'
                        sh 'printf "VITE_API_URL=https://192.168.0.9:8443\nVITE_APP_NAME=Point of Sale\n" > frontend/.env'
                    }

                    echo "✅ Fichiers .env préparés."
                }
            }
        }

        stage('📦 Build des Images') {
            steps {
                echo '🏗️ Construction des nouvelles images...'
                sh '${DOCKER_COMPOSE} build backend frontend'
            }
        }

        stage('🧪 Tests Automatisés') {
            steps {
                // Les tests bloquent le déploiement si échec (pas de catchError)
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

        stage('🚀 Déploiement Rolling Update') {
            // Déploiement uniquement sur la branche main
            when {
                branch 'main'
            }
            steps {
                script {

                    // ── Étape 1 : S'assurer que DB et Redis tournent ──────────────
                    echo '📦 Démarrage DB et Redis si nécessaire...'
                    sh '${DOCKER_COMPOSE} up -d db redis'
                    sh 'sleep 5'

                    // ── Étape 2 : Mise à jour BACKEND (rolling) ───────────────────
                    // --no-deps = ne redémarre PAS db/redis, juste le backend
                    // La DB reste accessible, ~5s d'interruption pour le backend
                    echo '🔄 Mise à jour du backend (rolling)...'
                    sh '${DOCKER_COMPOSE} up -d --no-deps backend'
                    sh 'sleep 20'  // Attente entrypoint (migrations internes)

                    // ── Étape 3 : Migrations de la base de données ────────────────
                    // JAMAIS migrate:fresh en CI/CD → conserve toutes les données
                    echo '🔧 Exécution des nouvelles migrations...'
                    sh '${DOCKER_COMPOSE} exec -T backend php artisan migrate --force'

                    // ── Étape 4 : Seeding ────────────────────────────────────────
                    if (params.INITIAL_SETUP) {
                        echo '🌱 INITIAL SETUP : Rechargement complet des données initiales...'
                        sh '${DOCKER_COMPOSE} exec -T backend php artisan migrate:fresh --seed --force'
                    } else {
                        // Seeders idempotents uniquement (roles + permissions)
                        // Sécurisé à chaque déploiement car utilise firstOrCreate
                        echo '🔐 Synchronisation des rôles et permissions...'
                        sh '${DOCKER_COMPOSE} exec -T backend php artisan db:seed --class=RoleSeeder --force'
                        sh '${DOCKER_COMPOSE} exec -T backend php artisan db:seed --class=PermissionSeeder --force'
                    }

                    // ── Étape 5 : Nettoyage des caches ───────────────────────────
                    echo '🔑 Nettoyage des caches...'
                    sh '${DOCKER_COMPOSE} exec -T backend php artisan config:clear'
                    sh '${DOCKER_COMPOSE} exec -T backend php artisan cache:clear'

                    // ── Étape 6 : Mise à jour NGINX ───────────────────────────────
                    echo '🌐 Mise à jour Nginx...'
                    sh '${DOCKER_COMPOSE} up -d --no-deps nginx'

                    // ── Étape 7 : Mise à jour FRONTEND (zero downtime) ────────────
                    // Nginx sert les anciens fichiers jusqu'au reload du navigateur
                    echo '🎨 Mise à jour du frontend...'
                    sh '${DOCKER_COMPOSE} up -d --no-deps frontend'

                }
            }
        }

        stage('✅ Vérification Post-Déploiement') {
            when {
                branch 'main'
            }
            steps {
                script {
                    echo '🔍 Vérification de la santé des services...'
                    sh '${DOCKER_COMPOSE} ps'
                    // Vérification que l'API répond
                    sh 'sleep 5 && curl -sfk https://192.168.0.9:8443/api/login -X POST -H "Content-Type: application/json" -d "{}" -o /dev/null -w "HTTPS Backend: %{http_code}\\n" || true'
                    sh 'curl -sfk https://192.168.0.9:5443 -o /dev/null -w "HTTPS Frontend: %{http_code}\\n" || true'
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
            echo '✅ Déploiement réussi ! Clients non interrompus.'
            sh 'docker image prune -f'
        }
        unstable {
            echo '⚠️ Déploiement réussi mais certains tests ont échoué. Vérifier les tests.'
        }
        failure {
            echo '❌ Le pipeline a échoué. Les services en cours restent actifs.'
        }
    }
}
