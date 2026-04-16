// ═══════════════════════════════════════════════════════════════════════════
//  Jenkinsfile — Pipeline CI/CD
//  Stack : PostgreSQL + Laravel + Vue.js
//  Cible : Docker Desktop / Kubernetes (WSL2 Windows 10)
// ═══════════════════════════════════════════════════════════════════════════

pipeline {

    agent any

    // ── Variables globales ─────────────────────────────────────────────────
    environment {
        REGISTRY        = "localhost:5000"           // Registry Docker local
        BACKEND_IMAGE   = "${REGISTRY}/backend"
        FRONTEND_IMAGE  = "${REGISTRY}/frontend"
        IMAGE_TAG       = "${env.BUILD_NUMBER}-${env.GIT_COMMIT?.take(7) ?: 'latest'}"
        KUBECONFIG      = credentials('kubeconfig') // Credential Jenkins
        K8S_NAMESPACE   = "devops-app"
    }

    options {
        timeout(time: 30, unit: 'MINUTES')
        buildDiscarder(logRotator(numToKeepStr: '10'))
        disableConcurrentBuilds()
    }

    stages {

        // ── 1. Checkout ────────────────────────────────────────────────────
        stage('Checkout') {
            steps {
                echo "📥 Récupération du code — branche: ${env.BRANCH_NAME}"
                checkout scm
            }
        }

        // ── 2. Tests Backend (Laravel) ─────────────────────────────────────
        stage('Tests Backend') {
            agent {
                docker {
                    image 'php:8.2-cli'
                    args  '--user root'
                    reuseNode true
                }
            }
            steps {
                dir('backend') {
                    sh '''
                        apt-get update -qq && apt-get install -y --no-install-recommends \
                            zip unzip libpq-dev git curl libpng-dev libonig-dev libzip-dev \
                            > /dev/null 2>&1
                        docker-php-ext-install pdo pdo_pgsql mbstring gd zip > /dev/null 2>&1
                        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
                        composer install --no-interaction --prefer-dist --optimize-autoloader
                        cp .env.example .env
                        php artisan key:generate
                        php artisan config:cache
                        php vendor/bin/phpunit --testdox --colors=always
                    '''
                }
            }
            post {
                always {
                    junit allowEmptyResults: true, testResults: 'backend/storage/junit*.xml'
                }
            }
        }

        // ── 3. Tests Frontend (Vue.js) ─────────────────────────────────────
        stage('Tests Frontend') {
            agent {
                docker {
                    image 'node:20-alpine'
                    reuseNode true
                }
            }
            steps {
                dir('frontend') {
                    sh '''
                        npm ci --prefer-offline
                        npm run lint --if-present
                        npm run test:unit --if-present -- --reporter=verbose
                    '''
                }
            }
        }

        // ── 4. Build images Docker ─────────────────────────────────────────
        stage('Build Images') {
            parallel {

                stage('Build Backend') {
                    steps {
                        dir('backend') {
                            script {
                                docker.build("${env.BACKEND_IMAGE}:${env.IMAGE_TAG}", \
                                    "--target final .")
                            }
                        }
                    }
                }

                stage('Build Frontend') {
                    steps {
                        dir('frontend') {
                            script {
                                docker.build("${env.FRONTEND_IMAGE}:${env.IMAGE_TAG}", ".")
                            }
                        }
                    }
                }
            }
        }

        // ── 5. Push vers le Registry ───────────────────────────────────────
        stage('Push Images') {
            steps {
                script {
                    // Tag "latest" sur la branche main
                    def isMain = env.BRANCH_NAME == 'main' || env.BRANCH_NAME == 'master'

                    docker.withRegistry("http://${env.REGISTRY}") {
                        def backendImg  = docker.image("${env.BACKEND_IMAGE}:${env.IMAGE_TAG}")
                        def frontendImg = docker.image("${env.FRONTEND_IMAGE}:${env.IMAGE_TAG}")

                        backendImg.push()
                        frontendImg.push()

                        if (isMain) {
                            backendImg.push('latest')
                            frontendImg.push('latest')
                        }
                    }
                }
            }
        }

        // ── 6. Déploiement Kubernetes ──────────────────────────────────────
        stage('Deploy to K8s') {
            when {
                anyOf {
                    branch 'main'
                    branch 'master'
                }
            }
            steps {
                script {
                    withCredentials([file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE')]) {
                        sh """
                            export KUBECONFIG=\${KUBECONFIG_FILE}

                            # Créer le namespace si absent
                            kubectl apply -f k8s/namespace.yaml

                            # Appliquer les Secrets et ConfigMaps
                            kubectl apply -f k8s/postgres/secret.yaml
                            kubectl apply -f k8s/backend/secret.yaml
                            kubectl apply -f k8s/backend/configmap.yaml

                            # PostgreSQL (PVC + déploiement + service)
                            kubectl apply -f k8s/postgres/pvc.yaml
                            kubectl apply -f k8s/postgres/deployment.yaml
                            kubectl apply -f k8s/postgres/service.yaml

                            # Attendre que Postgres soit prêt
                            kubectl rollout status deployment/postgres \
                                -n ${K8S_NAMESPACE} --timeout=120s

                            # Injecter le tag d'image dans les manifests et appliquer
                            sed -i "s|REGISTRY/backend:IMAGE_TAG|${env.BACKEND_IMAGE}:${env.IMAGE_TAG}|g" \
                                k8s/backend/deployment.yaml
                            sed -i "s|REGISTRY/frontend:IMAGE_TAG|${env.FRONTEND_IMAGE}:${env.IMAGE_TAG}|g" \
                                k8s/frontend/deployment.yaml

                            kubectl apply -f k8s/backend/
                            kubectl apply -f k8s/frontend/
                            kubectl apply -f k8s/ingress.yaml

                            # Vérifier le rollout
                            kubectl rollout status deployment/backend  \
                                -n ${K8S_NAMESPACE} --timeout=180s
                            kubectl rollout status deployment/frontend \
                                -n ${K8S_NAMESPACE} --timeout=120s

                            echo "✅ Déploiement terminé — image tag: ${env.IMAGE_TAG}"
                        """
                    }
                }
            }
        }
    }

    // ── Notifications post-pipeline ─────────────────────────────────────────
    post {
        success {
            echo "✅ Pipeline réussi — Build #${env.BUILD_NUMBER} — Tag: ${env.IMAGE_TAG}"
            // Ajouter ici : slackSend, emailext, etc.
        }
        failure {
            echo "❌ Pipeline échoué — consulter les logs ci-dessus"
        }
        always {
            // Nettoyage des images dangling
            sh 'docker image prune -f --filter "dangling=true" || true'
            cleanWs()
        }
    }
}
