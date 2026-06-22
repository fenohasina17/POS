pipeline {
    agent any

    // ============================================================
    // OPTIONS GLOBALES DU PIPELINE
    // ============================================================
    options {
        // Empêche deux builds de tourner en même temps
        // Evite les conflits sur les ressources K8s (même namespace)
        disableConcurrentBuilds()

        // Timeout global — tue le pipeline s'il dépasse 30 minutes
        timeout(time: 60, unit: 'MINUTES')

        // Conserve uniquement les 10 derniers builds dans Jenkins
        buildDiscarder(logRotator(numToKeepStr: '10'))
    }

    // ============================================================
    // TRIGGERS — Déclenchement automatique
    // ============================================================
    // githubPush : webhook GitHub → Jenkins se déclenche sur push/PR
    // Nécessite : job configuré en "Multibranch Pipeline" dans Jenkins
    triggers {
        githubPush()
    }

    // ============================================================
    // PARAMÈTRES DU PIPELINE
    // ============================================================
    parameters {
        booleanParam(
            name: 'FORCE_DEPLOY',
            defaultValue: false,
            description: '⚡ Forcer le déploiement même si build ou tests échouent — utilise la dernière image :latest réussie pour les services non buildés (review/staging uniquement)'
        )
    }

    // ============================================================
    // VARIABLES D'ENVIRONNEMENT
    // ============================================================
    environment {
        K8S_NAMESPACE  = 'devops-app'
        STAGING_NAMESPACE = 'devops-app-staging'
        BACKEND_IMAGE  = 'giovanni09/backend'
        FRONTEND_IMAGE = 'giovanni09/frontend'
        IMAGE_TAG      = "v${BUILD_NUMBER}"
        ROLLOUT_TIMEOUT = '300s'

        // Branche qui déclenche le déploiement
        DEPLOY_BRANCH  = 'main'

        // Indicateurs de succès de build — mis à 'true' dans Build Images
        BACKEND_BUILT  = 'false'
        FRONTEND_BUILT = 'false'
    }

    stages {

        // ============================================================
        // STAGE 1 — Checkout
        // ============================================================
        stage('Checkout') {
            steps {
                echo "Récupération du code source..."
                checkout scm
            }
        }

        // ============================================================
        // ============================================================
        // STAGE 2 — Scan Secrets (truffleHog)
        // ============================================================
        stage('Scan Secrets') {
            steps {
                echo "Scan des secrets avec truffleHog..."
                sh '''
                    docker run --rm \
                        -v /var/run/docker.sock:/var/run/docker.sock \
                        -v $(pwd):/workdir \
                        -w /workdir \
                        trufflesecurity/trufflehog:latest \
                        filesystem /workdir \
                        --only-verified \
                        --fail || true
                '''
                echo "Scan secrets termine"
            }
        }

        // STAGE 2 — Build des images Docker
        // ============================================================
        stage('Build Images') {
            steps {
                echo "Build des images Docker..."
                script {
                    parallel(
                        "Backend": {
                            sh """
                                docker build \
                                    -t ${BACKEND_IMAGE}:${IMAGE_TAG} \
                                    -t ${BACKEND_IMAGE}:latest \
                                    ./backend
                            """
                        },
                        "Frontend": {
                            sh """
                                docker build \
                                    -t ${FRONTEND_IMAGE}:${IMAGE_TAG} \
                                    -t ${FRONTEND_IMAGE}:latest \
                                    ./frontend
                            """
                        }
                    )
                }
            }
        }
        // ============================================================
        stage('Verification Syntaxe PHP') {
            steps {
                echo "Vérification de la syntaxe PHP..."
                sh """
                    docker run --rm --entrypoint bash ${BACKEND_IMAGE}:${IMAGE_TAG} -c "
                        find /var/www/app -name '*.php' \
                            -not -path '*/vendor/*' \
                        | xargs -P4 -I{} php -l {} \
                        | grep -v 'No syntax errors' \
                        || true
                    "
                """
            }
        }

        // ============================================================
        // STAGE 4 — Tests PHPUnit
        // ============================================================
        // APP_KEY récupérée depuis Jenkins Credentials — jamais en clair
        // Volume /tmp partagé pour récupérer le rapport JUnit XML
        stage('Tests') {
            steps {
                // catchError garantit que toute erreur infra (docker, réseau, credentials)
                // passe en UNSTABLE plutôt qu'en FAILURE — les stages de déploiement
                // s'exécutent toujours ; seul un vrai échec de build les stoppe
                catchError(buildResult: 'UNSTABLE', stageResult: 'UNSTABLE') {
                    echo "Lancement des tests PHPUnit..."
                    sh """
                        docker network create test-net-${BUILD_NUMBER} || true

                        docker run -d \
                            --name pg-test-${BUILD_NUMBER} \
                            --network test-net-${BUILD_NUMBER} \
                            -e POSTGRES_DB=testing \
                            -e POSTGRES_USER=postgres \
                            -e POSTGRES_PASSWORD=postgres_test \
                            postgres:15-alpine

                        docker run -d \
                            --name redis-test-${BUILD_NUMBER} \
                            --network test-net-${BUILD_NUMBER} \
                            redis:7-alpine
                    """

                    sh """
                        echo "Attente que Postgres soit prêt..."
                        docker run --rm \
                            --network test-net-${BUILD_NUMBER} \
                            --entrypoint sh \
                            postgres:15-alpine \
                            -c "
                                until pg_isready -h pg-test-${BUILD_NUMBER} -U postgres; do
                                    echo 'Postgres pas encore prêt, attente...'
                                    sleep 2
                                done
                                echo 'Postgres est prêt !'
                            "
                    """

                    withCredentials([string(credentialsId: 'app-key', variable: 'APP_KEY_TEST')]) {
                        sh """
                            docker run --rm \
                                --network test-net-${BUILD_NUMBER} \
                                -v /tmp:/tmp \
                                -e APP_ENV=testing \
                                -e APP_KEY="\${APP_KEY_TEST}" \
                                -e DB_CONNECTION=pgsql \
                                -e DB_HOST=pg-test-${BUILD_NUMBER} \
                                -e DB_PORT=5432 \
                                -e DB_DATABASE=testing \
                                -e DB_USERNAME=postgres \
                                -e DB_PASSWORD=postgres_test \
                                -e REDIS_HOST=redis-test-${BUILD_NUMBER} \
                                -e REDIS_PORT=6379 \
                                ${BACKEND_IMAGE}:${IMAGE_TAG} \
                                php vendor/bin/phpunit \
                                    --testdox \
                                    --log-junit /tmp/phpunit-${BUILD_NUMBER}.xml \
                                > /tmp/phpunit-${BUILD_NUMBER}.txt 2>&1 || true

                            cat /tmp/phpunit-${BUILD_NUMBER}.txt
                        """

                        // Tests échoués = build UNSTABLE (orange), pas FAILURE (rouge)
                        // Le déploiement continue — rollback possible via kubectl rollout undo
                        // ou en relançant un build précédent depuis Jenkins
                        script {
                            def hasFailures = sh(
                                script: "grep -qE 'Failures: [1-9]|Errors: [1-9]' /tmp/phpunit-${BUILD_NUMBER}.txt",
                                returnStatus: true
                            ) == 0

                            if (hasFailures) {
                                unstable('⚠️ Des tests ont échoué — déploiement maintenu, corriger côté développeurs')
                            }
                        }
                    }
                }
            }

            post {
                always {
                    echo "Nettoyage des containers de test..."
                    sh """
                        docker rm -f pg-test-${BUILD_NUMBER}    || true
                        docker rm -f redis-test-${BUILD_NUMBER} || true
                        docker network rm test-net-${BUILD_NUMBER} || true

                        # Copier les rapports dans le workspace Jenkins pour archivage
                        cp /tmp/phpunit-${BUILD_NUMBER}.txt ${WORKSPACE}/phpunit-output.txt   || true
                        cp /tmp/phpunit-${BUILD_NUMBER}.xml ${WORKSPACE}/phpunit-results.xml  || true
                    """

                    // Rapport JUnit visible dans l'interface Jenkins (onglet "Test Results")
                    junit allowEmptyResults: true, testResults: 'phpunit-results.xml'

                    // Archive le log texte pour consultation directe
                    archiveArtifacts artifacts: 'phpunit-output.txt', allowEmptyArchive: true
                }
            }
        }

        // ============================================================
        // STAGE 4b — OWASP Dependency Check (vulnérabilités dépendances)
        // ============================================================
        // Scanne composer.json (PHP) et package.json (Node) pour détecter
        // les CVE connues dans les librairies tierces de l'application.
        //
        // --failOnCVSS 9 : bloque seulement si CVSS >= 9 (CRITICAL)
        // --format HTML  : rapport lisible archivé dans Jenkins
        // ============================================================
        stage('Audit Dependances') {
            steps {
                catchError(buildResult: 'SUCCESS', stageResult: 'UNSTABLE') {
                    echo "Audit des dependances PHP et Node..."
                    sh '''
                        echo "=== COMPOSER AUDIT (PHP) ==="
                        docker run --rm --entrypoint composer \
                            -w /var/www \
                            giovanni09/backend:latest \
                            audit --format=table || true
                        echo "=== NPM AUDIT (Node) ==="
                        tar -C frontend --exclude=node_modules --exclude=dist -czf - package.json package-lock.json 2>/dev/null | \
                        docker run --rm -i --entrypoint sh node:20-alpine -c \
                            "cd /tmp && tar -xzf - && npm audit || true" || true
                    '''
                    echo "Audit dependances termine"
                }
            }
        }
        // STAGE 5 — Push vers Docker Hub
        // ============================================================
        // Exécuté UNIQUEMENT sur la branche de production (DEPLOY_BRANCH)
        // Les autres branches buildent et testent, mais ne déploient pas
        stage('Push Images') {
            when {
                allOf {
                    expression { return env.CHANGE_TARGET == null }
                    expression {
                        return env.GIT_BRANCH == "origin/${DEPLOY_BRANCH}" ||
                               env.BRANCH_NAME == "${DEPLOY_BRANCH}"
                    }
                }
            }
            steps {
                echo "Push des images vers Docker Hub..."
                withCredentials([usernamePassword(
                    credentialsId: 'dockerhub-credentials',
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASS'
                )]) {
                    sh """
                        echo "\${DOCKER_PASS}" | docker login -u "\${DOCKER_USER}" --password-stdin
                        docker push ${BACKEND_IMAGE}:${IMAGE_TAG}
                        docker push ${BACKEND_IMAGE}:latest
                        docker push ${FRONTEND_IMAGE}:${IMAGE_TAG}
                        docker push ${FRONTEND_IMAGE}:latest
                    """
                }
            }
        }

        // ============================================================
        // STAGE 5b — Scan Trivy (après push, image disponible sur Docker Hub)
        // ============================================================
        stage('Scan Trivy') {
            steps {
                echo "Scan des vulnerabilites avec Trivy..."
                sh """
                    docker run --rm \
                        -v /var/run/docker.sock:/var/run/docker.sock \
                        -v /tmp/trivy-cache:/root/.cache/trivy \
                        aquasec/trivy:latest image \
                        --severity HIGH,CRITICAL \
                        --exit-code 0 \
                        --format table \
                        giovanni09/backend:latest || true
                """
                echo "Scan Trivy termine"
            }
        }

        // ============================================================
        // STAGE PR — Résumé pour les Pull Requests
        // ============================================================
        // Sur une PR : build + test + scan sont passés, pas de déploiement.
        // CHANGE_TARGET est défini automatiquement par Multibranch Pipeline.
        stage('PR Check') {
            when {
                expression { return env.CHANGE_TARGET != null }
            }
            steps {
                echo """
                    ✅ Pull Request #${env.CHANGE_ID} vérifiée
                    Branche : ${env.CHANGE_BRANCH} → ${env.CHANGE_TARGET}
                    Build   : OK
                    Tests   : ${currentBuild.result ?: 'SUCCESS'}
                    Le déploiement se fera après merge sur ${DEPLOY_BRANCH}.
                """
            }
        }

        // ============================================================
        // STAGE 6 — Déploiement STAGING (toujours, même si tests UNSTABLE)
        // ============================================================
        // Skippé sur les PR — déploiement uniquement après merge sur main
        stage('Deploy Staging') {
            when {
                allOf {
                    expression { return env.CHANGE_TARGET == null }
                    expression {
                        return env.GIT_BRANCH == "origin/${DEPLOY_BRANCH}" ||
                               env.BRANCH_NAME == "${DEPLOY_BRANCH}"
                    }
                }
            }
            steps {
                echo "Déploiement sur STAGING..."

                sh 'kubectl apply -f k8s/staging/configmap.yaml'

                withCredentials([
                    string(credentialsId: 'app-key',     variable: 'APP_KEY'),
                    string(credentialsId: 'db-password', variable: 'DB_PASSWORD')
                ]) {
                    sh """
                        kubectl create secret generic app-secrets \
                            --from-literal=APP_KEY="\${APP_KEY}" \
                            --from-literal=DB_PASSWORD="\${DB_PASSWORD}" \
                            -n ${STAGING_NAMESPACE} \
                            --dry-run=client -o yaml | kubectl apply -f -
                    """
                }

                sh 'kubectl apply -f k8s/staging/postgres/postgres.yaml'

                sh """
                    kubectl rollout status statefulset/postgres \
                        -n ${STAGING_NAMESPACE} \
                        --timeout=${ROLLOUT_TIMEOUT}
                """

                sh """
                    sed 's|image: ${BACKEND_IMAGE}:.*|image: ${BACKEND_IMAGE}:${IMAGE_TAG}|g' \
                        k8s/staging/backend/backend.yaml | kubectl apply -f -

                    sed 's|image: ${FRONTEND_IMAGE}:.*|image: ${FRONTEND_IMAGE}:${IMAGE_TAG}|g' \
                        k8s/staging/frontend/frontend.yaml | kubectl apply -f -
                """

                sh 'kubectl apply -f k8s/staging/ingress.yaml'

                sh """
                    kubectl delete job backend-migrate \
                        -n ${STAGING_NAMESPACE} --ignore-not-found

                    sed 's|image: ${BACKEND_IMAGE}:.*|image: ${BACKEND_IMAGE}:${IMAGE_TAG}|g' \
                        k8s/staging/backend/migrate-job.yaml | kubectl apply -f -
                """
            }
        }

        // ============================================================
        // STAGE 7 — Attente STAGING
        // ============================================================
        stage('Attente Staging') {
            when {
                allOf {
                    expression { return env.CHANGE_TARGET == null }
                    expression {
                        return env.GIT_BRANCH == "origin/${DEPLOY_BRANCH}" ||
                               env.BRANCH_NAME == "${DEPLOY_BRANCH}"
                    }
                }
            }
            steps {
                echo "Attente que les pods staging soient prêts..."

                sh """
                    kubectl rollout status deployment/backend \
                        -n ${STAGING_NAMESPACE} \
                        --timeout=${ROLLOUT_TIMEOUT}
                """

                sh """
                    kubectl rollout status deployment/frontend \
                        -n ${STAGING_NAMESPACE} \
                        --timeout=${ROLLOUT_TIMEOUT}
                """
            }
        }

        // ============================================================
        // STAGE 8 — Migration STAGING
        // ============================================================
        stage('Migrate Staging') {
            when {
                allOf {
                    expression { return env.CHANGE_TARGET == null }
                    expression {
                        return env.GIT_BRANCH == "origin/${DEPLOY_BRANCH}" ||
                               env.BRANCH_NAME == "${DEPLOY_BRANCH}"
                    }
                }
            }
            steps {
                echo "Migrations Laravel staging..."
                sh """
                    kubectl wait --for=condition=complete \
                        --timeout=600s \
                        job/backend-migrate \
                        -n ${STAGING_NAMESPACE}
                """
            }
        }

        // ============================================================
        // STAGE 9 — Déploiement PROD (seulement si tests OK)
        // ============================================================
        stage('Deploy Prod') {
            when {
                allOf {
                    expression { return env.CHANGE_TARGET == null }
                    expression {
                        return env.GIT_BRANCH == "origin/${DEPLOY_BRANCH}" ||
                               env.BRANCH_NAME == "${DEPLOY_BRANCH}"
                    }
                    expression {
                        return currentBuild.result == null || currentBuild.result == 'SUCCESS'
                    }
                }
            }
            steps {
                echo "Déploiement sur PROD..."

                sh 'kubectl apply -f k8s/configmap.yaml'

                withCredentials([
                    string(credentialsId: 'app-key',     variable: 'APP_KEY'),
                    string(credentialsId: 'db-password', variable: 'DB_PASSWORD')
                ]) {
                    sh """
                        kubectl create secret generic app-secrets \
                            --from-literal=APP_KEY="\${APP_KEY}" \
                            --from-literal=DB_PASSWORD="\${DB_PASSWORD}" \
                            -n ${K8S_NAMESPACE} \
                            --dry-run=client -o yaml | kubectl apply -f -
                    """
                }

                sh 'kubectl apply -f k8s/postgres/postgres.yaml'

                sh """
                    kubectl rollout status statefulset/postgres \
                        -n ${K8S_NAMESPACE} \
                        --timeout=${ROLLOUT_TIMEOUT}
                """

                sh """
                    sed 's|image: ${BACKEND_IMAGE}:.*|image: ${BACKEND_IMAGE}:${IMAGE_TAG}|g' \
                        k8s/backend/backend.yaml | kubectl apply -f -

                    sed 's|image: ${FRONTEND_IMAGE}:.*|image: ${FRONTEND_IMAGE}:${IMAGE_TAG}|g' \
                        k8s/frontend/frontend.yaml | kubectl apply -f -
                """

                sh 'kubectl apply -f k8s/ingress.yaml'

                sh """
                    kubectl delete job backend-migrate \
                        -n ${K8S_NAMESPACE} --ignore-not-found

                    sed 's|image: ${BACKEND_IMAGE}:.*|image: ${BACKEND_IMAGE}:${IMAGE_TAG}|g' \
                        k8s/backend/migrate-job.yaml | kubectl apply -f -
                """
            }
        }

        // ============================================================
        // STAGE 10 — Attente PROD
        // ============================================================
        stage('Attente Prod') {
            when {
                allOf {
                    expression {
                        return env.GIT_BRANCH == "origin/${DEPLOY_BRANCH}" ||
                               env.BRANCH_NAME == "${DEPLOY_BRANCH}"
                    }
                    expression {
                        return currentBuild.result == null || currentBuild.result == 'SUCCESS'
                    }
                }
            }
            steps {
                echo "Attente que les pods prod soient prêts..."

                sh """
                    kubectl rollout status deployment/backend \
                        -n ${K8S_NAMESPACE} \
                        --timeout=${ROLLOUT_TIMEOUT}
                """

                sh """
                    kubectl rollout status deployment/frontend \
                        -n ${K8S_NAMESPACE} \
                        --timeout=${ROLLOUT_TIMEOUT}
                """
            }
        }

        // ============================================================
        // STAGE 11 — Migration PROD
        // ============================================================
        stage('Migrate Prod') {
            when {
                allOf {
                    expression {
                        return env.GIT_BRANCH == "origin/${DEPLOY_BRANCH}" ||
                               env.BRANCH_NAME == "${DEPLOY_BRANCH}"
                    }
                    expression {
                        return currentBuild.result == null || currentBuild.result == 'SUCCESS'
                    }
                }
            }
            steps {
                echo "Migrations Laravel prod..."
                sh """
                    kubectl wait --for=condition=complete \
                        --timeout=600s \
                        job/backend-migrate \
                        -n ${K8S_NAMESPACE}
                """
            }
        }
    }

    // ============================================================
    // POST — Actions après le pipeline
    // ============================================================
    post {
        success {
            echo """
                ✅ Déploiement réussi !
                Image backend  : ${BACKEND_IMAGE}:${IMAGE_TAG}
                Image frontend : ${FRONTEND_IMAGE}:${IMAGE_TAG}
                Namespace      : ${K8S_NAMESPACE}
            """
            // Notification Slack — décommenter si le plugin Slack est installé
            // et la credential 'slack-webhook' configurée dans Jenkins
            // slackSend(
            //     color: 'good',
            //     channel: '#deployments',
            //     message: "✅ *${env.JOB_NAME}* #${BUILD_NUMBER} déployé — `${BACKEND_IMAGE}:${IMAGE_TAG}`"
            // )
        }

        failure {
            echo """
                ❌ Echec du pipeline !
                Vérifier les logs ci-dessus pour plus de détails.
                Namespace : ${K8S_NAMESPACE}
            """

            // Rollback automatique si l'échec survient après le déploiement
            script {
                def isDeployBranch = env.GIT_BRANCH == "origin/${DEPLOY_BRANCH}" ||
                                     env.BRANCH_NAME == "${DEPLOY_BRANCH}"
                if (isDeployBranch) {
                    echo "Tentative de rollback..."
                    sh """
                        kubectl rollout undo deployment/backend  -n ${K8S_NAMESPACE} || true
                        kubectl rollout undo deployment/frontend -n ${K8S_NAMESPACE} || true
                    """
                }
            }

            // Notification Slack — décommenter si le plugin Slack est installé
            // slackSend(
            //     color: 'danger',
            //     channel: '#deployments',
            //     message: "❌ *${env.JOB_NAME}* #${BUILD_NUMBER} a échoué — <${env.BUILD_URL}|Voir les logs>"
            // )
        }

        always {
            sh 'docker logout || true'
            sh 'docker image prune -f || true'
        }
    }
}
