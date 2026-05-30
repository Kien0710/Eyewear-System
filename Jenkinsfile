pipeline {
    agent any

    options {
        timestamps()
        skipDefaultCheckout(false)
        timeout(time: 30, unit: 'MINUTES')
        buildDiscarder(logRotator(numToKeepStr: '20'))
    }

    environment {
        BACKEND_DIR  = 'backend'
        FRONTEND_DIR = 'frontend'
        PROJECT_KEY  = 'ESQ'
        SONAR_PROJECT_KEY  = 'eyewear-system'
        SONAR_PROJECT_NAME = 'Eyewear System'
    }



    stages {

        // ─────────────────────────────────────────────────────────
        // 1. CHECKOUT
        // ─────────────────────────────────────────────────────────
        stage('Checkout') {
            steps {
                echo '📥 Checking out Eyewear System source code...'
                checkout scm
            }
        }

        // ─────────────────────────────────────────────────────────
        // 2. PROJECT STRUCTURE CHECK
        // ─────────────────────────────────────────────────────────
        stage('Project Structure Check') {
            steps {
                echo '📁 Verifying required project files...'
                script {
                    def requiredPaths = [
                        'backend/app',
                        'backend/core',
                        'backend/routes/api.php',
                        'backend/database/schema.sql',
                        'backend/database/seeder.php',
                        'backend/public/index.php',
                        'frontend/index.html',
                        'frontend/js',
                        'frontend/assets',
                        'Eyewear-System.postman_collection.json'
                    ]
                    requiredPaths.each { path ->
                        if (!fileExists(path)) {
                            error "❌ Required path is missing: ${path}"
                        }
                    }
                    echo '✅ All required files found.'
                }
            }
        }

        // ─────────────────────────────────────────────────────────
        // 3. PHP SYNTAX CHECK
        // ─────────────────────────────────────────────────────────
        stage('Backend PHP Syntax Check') {
            steps {
                echo '🔍 Checking PHP syntax...'
                script {
                    if (isUnix()) {
                        sh '''
                            if command -v php > /dev/null 2>&1; then
                                ERRORS=0
                                while IFS= read -r -d '' file; do
                                    if ! php -l "$file" > /dev/null 2>&1; then
                                        php -l "$file"
                                        ERRORS=$((ERRORS + 1))
                                    fi
                                done < <(find backend -name "*.php" -print0)
                                if [ $ERRORS -gt 0 ]; then
                                    echo "❌ Found $ERRORS PHP syntax error(s)."
                                    exit 1
                                fi
                                echo "✅ All PHP files pass syntax check."
                            else
                                echo "⚠️ PHP CLI not found. Skipping syntax check."
                            fi
                        '''
                    } else {
                        bat '''
                            where php >nul 2>nul
                            if %ERRORLEVEL% EQU 0 (
                                for /R backend %%f in (*.php) do php -l "%%f"
                            ) else (
                                echo PHP CLI not found. Skipping syntax check.
                            )
                            exit /b 0
                        '''
                    }
                }
            }
        }

        // ─────────────────────────────────────────────────────────
        // 4. FRONTEND STATIC CHECK
        // ─────────────────────────────────────────────────────────
        stage('Frontend Static File Check') {
            steps {
                echo '🌐 Checking frontend static entry files...'
                script {
                    def requiredFrontendFiles = [
                        'frontend/index.html',
                        'frontend/assets/css/styles.css',
                        'frontend/js/main.js',
                        'frontend/js/services/apiClient.js'
                    ]
                    requiredFrontendFiles.each { path ->
                        if (!fileExists(path)) {
                            error "❌ Required frontend file is missing: ${path}"
                        }
                    }
                    echo '✅ Frontend entry files OK.'
                }
            }
        }

        // ─────────────────────────────────────────────────────────
        // 5. TEST DOCUMENTATION CHECK
        // ─────────────────────────────────────────────────────────
        stage('Test Documentation Check') {
            steps {
                echo '📋 Checking test and Postman assets...'
                script {
                    def testAssets = [
                        'backend/tests/Feature',
                        'Eyewear-System.postman_collection.json'
                    ]
                    testAssets.each { path ->
                        if (!fileExists(path)) {
                            error "❌ Test asset missing: ${path}"
                        }
                    }
                    echo '✅ Test assets OK.'
                }
            }
        }

        // ─────────────────────────────────────────────────────────
        // 6. SONARQUBE ANALYSIS
        // Requires: SonarQube Scanner plugin + credential 'SONAR_TOKEN'
        // ─────────────────────────────────────────────────────────
        stage('SonarQube Analysis') {
            steps {
                echo '📊 Running SonarQube code quality analysis...'
                withCredentials([string(credentialsId: 'SONAR_TOKEN', variable: 'SONAR_AUTH_TOKEN')]) {
                    withSonarQubeEnv('SonarQube') {
                        script {
                            def scannerHome = tool 'sonar-scanner'
                            if (isUnix()) {
                                sh """
                                    "${scannerHome}/bin/sonar-scanner" \
                                        -Dsonar.projectKey=${SONAR_PROJECT_KEY} \
                                        -Dsonar.projectName="${SONAR_PROJECT_NAME}" \
                                        -Dsonar.projectVersion=1.0.${BUILD_NUMBER} \
                                        -Dsonar.sources=backend/app,backend/core,backend/routes,frontend/js \
                                        -Dsonar.exclusions=**/vendor/**,**/node_modules/**,**/*.min.js \
                                        -Dsonar.php.file.suffixes=php \
                                        -Dsonar.login=${SONAR_AUTH_TOKEN}
                                """
                            } else {
                                bat """
                                    "${scannerHome}\\bin\\sonar-scanner.bat" ^
                                        -Dsonar.projectKey=%SONAR_PROJECT_KEY% ^
                                        -Dsonar.projectName="%SONAR_PROJECT_NAME%" ^
                                        -Dsonar.projectVersion=1.0.%BUILD_NUMBER% ^
                                        -Dsonar.sources=backend/app,backend/core,backend/routes,frontend/js ^
                                        -Dsonar.exclusions=**/vendor/**,**/node_modules/**,**/*.min.js ^
                                        -Dsonar.php.file.suffixes=php ^
                                        -Dsonar.login=%SONAR_AUTH_TOKEN%
                                """
                            }
                        }
                    }
                }
            }
        }

        // ─────────────────────────────────────────────────────────
        // 7. SONARQUBE QUALITY GATE
        // Chờ SonarQube trả kết quả pass/fail
        // ─────────────────────────────────────────────────────────
        stage('Quality Gate') {
            steps {
                echo '🚦 Waiting for SonarQube Quality Gate result...'
                timeout(time: 5, unit: 'MINUTES') {
                    waitForQualityGate abortPipeline: false
                }
            }
        }

        // ─────────────────────────────────────────────────────────
        // 8. NOTIFY JIRA
        // Đăng kết quả build vào Jira dùng REST API v3
        // Requires credential: JIRA_CREDS (username:api_token)
        // ─────────────────────────────────────────────────────────
        stage('Notify Jira') {
            steps {
                echo '📌 Posting build result to Jira...'
                withCredentials([
                    usernamePassword(
                        credentialsId: 'JIRA_CREDS',
                        usernameVariable: 'JIRA_USER',
                        passwordVariable: 'JIRA_TOKEN'
                    ),
                    string(credentialsId: 'JIRA_BASE_URL', variable: 'JIRA_URL')
                ]) {
                    script {
                        def buildStatus  = currentBuild.currentResult ?: 'SUCCESS'
                        def emoji        = buildStatus == 'SUCCESS' ? '✅' : '❌'
                        def buildUrl     = env.BUILD_URL ?: 'N/A'
                        def branch       = env.GIT_BRANCH ?: env.BRANCH_NAME ?: 'unknown'
                        def sonarUrl     = env.SONAR_HOST_URL ? "${env.SONAR_HOST_URL}/dashboard?id=${SONAR_PROJECT_KEY}" : 'N/A'

                        def commentLines = [
                            "${emoji} Jenkins Build #${BUILD_NUMBER} — ${buildStatus}",
                            "Branch: ${branch}",
                            "Build URL: ${buildUrl}",
                            "SonarQube Report: ${sonarUrl}",
                            "Triggered by: ${currentBuild.getBuildCauses()[0]?.shortDescription ?: 'unknown'}"
                        ]

                        def adfBody = groovy.json.JsonOutput.toJson([
                            version: 1,
                            type: 'doc',
                            content: commentLines.collect { line ->
                                [type: 'paragraph', content: [[type: 'text', text: line]]]
                            }
                        ])

                        // Lấy danh sách bug đang mở của project
                        def jqlEncoded = java.net.URLEncoder.encode(
                            "project = ${PROJECT_KEY} AND statusCategory != Done AND issuetype = Bug AND labels = ci-cd",
                            'UTF-8'
                        )
                        def jiraBase = JIRA_URL.replaceAll('/+$', '')
                        def authB64  = "${JIRA_USER}:${JIRA_TOKEN}".bytes.encodeBase64().toString()

                        if (isUnix()) {
                            sh """
                                ISSUES=\$(curl -s -X GET \\
                                    -H "Authorization: Basic ${authB64}" \\
                                    -H "Content-Type: application/json" \\
                                    "${jiraBase}/rest/api/3/search/jql?jql=${jqlEncoded}&maxResults=50&fields=summary" \\
                                    | grep -o '"key":"[^"]*"' | grep -o 'ESQ-[0-9]*' | head -5)

                                echo "Found Jira issues: \$ISSUES"

                                for KEY in \$ISSUES; do
                                    curl -s -X POST \\
                                        -H "Authorization: Basic ${authB64}" \\
                                        -H "Content-Type: application/json" \\
                                        -d '{"body": ${adfBody}}' \\
                                        "${jiraBase}/rest/api/3/issue/\$KEY/comment" > /dev/null
                                    echo "📌 Notified Jira issue: \$KEY"
                                done
                            """
                        } else {
                            echo "⚠️ Jira notification via curl skipped on Windows agent. Configure Jira plugin instead."
                        }
                    }
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // POST ACTIONS
    // ─────────────────────────────────────────────────────────────
    post {
        always {
            echo '📦 Archiving artifacts...'
            archiveArtifacts(
                artifacts: 'README.md,docs/**/*.md,docs/**/*.docx,Eyewear-System.postman_collection.json',
                allowEmptyArchive: true
            )
        }
        success {
            echo '🎉 Pipeline PASSED — Eyewear System build successful.'
        }
        failure {
            echo '🔥 Pipeline FAILED — Check console log for details.'
        }
        unstable {
            echo '⚠️ Pipeline UNSTABLE — Quality Gate may have warnings.'
        }
    }
}
