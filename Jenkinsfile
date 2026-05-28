pipeline {
    agent any

    options {
        timestamps()
        skipDefaultCheckout(false)
    }

    environment {
        BACKEND_DIR = 'backend'
        FRONTEND_DIR = 'frontend'
    }

    stages {
        stage('Checkout') {
            steps {
                echo 'Checking out Eyewear System source code...'
                checkout scm
            }
        }

        stage('Project Structure Check') {
            steps {
                echo 'Verifying required project directories and files...'
                script {
                    def requiredPaths = [
                        'backend/app',
                        'backend/core',
                        'backend/routes/api.php',
                        'backend/database/schema.sql',
                        'backend/public/index.php',
                        'frontend/index.html',
                        'frontend/js',
                        'frontend/assets',
                        'Eyewear-System.postman_collection.json'
                    ]

                    requiredPaths.each { path ->
                        if (!fileExists(path)) {
                            error "Required path is missing: ${path}"
                        }
                    }
                }
            }
        }

        stage('Backend PHP Syntax Check') {
            steps {
                echo 'Checking PHP syntax when PHP CLI is available...'
                script {
                    if (isUnix()) {
                        sh '''
                            if command -v php >/dev/null 2>&1; then
                                find backend -name "*.php" -print0 | xargs -0 -n1 php -l
                            else
                                echo "PHP CLI is not installed on this Jenkins agent. Skipping PHP syntax check."
                            fi
                        '''
                    } else {
                        bat '''
                            where php >nul 2>nul
                            if %ERRORLEVEL% EQU 0 (
                                for /R backend %%f in (*.php) do php -l "%%f"
                            ) else (
                                echo PHP CLI is not installed on this Jenkins agent. Skipping PHP syntax check.
                            )
                            exit /b 0
                        '''
                    }
                }
            }
        }

        stage('Frontend Static File Check') {
            steps {
                echo 'Checking frontend static entry files...'
                script {
                    def requiredFrontendFiles = [
                        'frontend/index.html',
                        'frontend/assets/css/styles.css',
                        'frontend/js/main.js',
                        'frontend/js/services/apiClient.js'
                    ]

                    requiredFrontendFiles.each { path ->
                        if (!fileExists(path)) {
                            error "Required frontend file is missing: ${path}"
                        }
                    }
                }
            }
        }

        stage('Test Documentation Check') {
            steps {
                echo 'Checking test and Postman assets...'
                script {
                    def testAssets = [
                        'backend/tests/Feature',
                        'Eyewear-System.postman_collection.json'
                    ]

                    testAssets.each { path ->
                        if (!fileExists(path)) {
                            error "Test asset is missing: ${path}"
                        }
                    }
                }
            }
        }
    }

    post {
        always {
            echo 'Archiving documentation and Postman collection...'
            archiveArtifacts artifacts: 'README.md,docs/**/*.md,docs/**/*.docx,Eyewear-System.postman_collection.json', allowEmptyArchive: true
        }
        success {
            echo 'Eyewear System pipeline completed successfully.'
        }
        failure {
            echo 'Eyewear System pipeline failed. Check the Jenkins console log for details.'
        }
    }
}
