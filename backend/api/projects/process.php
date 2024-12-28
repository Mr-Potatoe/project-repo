<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../config/database.php';

function sendResponse($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function findSqlFiles($dir) {
    $sqlFiles = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'sql') {
            $sqlFiles[] = $file->getPathname();
        }
    }
    
    // Sort files to ensure proper execution order
    sort($sqlFiles);
    return $sqlFiles;
}

function executeSqlFile($db, $sqlFile) {
    try {
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new Exception("Failed to read SQL file: " . $sqlFile);
        }

        // Log the SQL file being processed
        error_log("Processing SQL file: " . basename($sqlFile));

        // Remove any UTF-8 BOM if present
        $sql = str_replace("\xEF\xBB\xBF", '', $sql);
        
        // Handle MySQL specific syntax
        $sql = str_replace('/*!40101 SET', '-- /*!40101 SET', $sql);
        $sql = str_replace('/*!40014 SET', '-- /*!40014 SET', $sql);
        $sql = str_replace('/*!40111 SET', '-- /*!40111 SET', $sql);
        
        // Split SQL file into individual statements
        $statements = [];
        $currentStatement = '';
        
        foreach (explode("\n", $sql) as $line) {
            // Skip comments and empty lines
            if (preg_match('/^\s*--/', $line) || trim($line) === '') {
                continue;
            }
            
            $currentStatement .= $line . "\n";
            
            if (substr(trim($line), -1) === ';') {
                $statements[] = trim($currentStatement);
                $currentStatement = '';
            }
        }

        // Execute each statement
        foreach ($statements as $statement) {
            try {
                // Skip certain statements
                if (
                    stripos($statement, 'SET SQL_MODE') !== false ||
                    stripos($statement, 'SET time_zone') !== false ||
                    stripos($statement, 'SET CHARACTER_SET_CLIENT') !== false ||
                    stripos($statement, 'SET CHARACTER_SET_RESULTS') !== false ||
                    stripos($statement, 'SET COLLATION_CONNECTION') !== false ||
                    stripos($statement, 'SET NAMES') !== false ||
                    stripos($statement, 'START TRANSACTION') !== false ||
                    stripos($statement, 'COMMIT') !== false
                ) {
                    continue;
                }

                // Handle CREATE TABLE statements
                if (stripos($statement, 'CREATE TABLE') !== false) {
                    // Extract table name
                    preg_match('/CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`?(\w+)`?/i', $statement, $matches);
                    if (isset($matches[1])) {
                        $tableName = $matches[1];
                        // Check if table exists
                        $checkTable = $db->query("SHOW TABLES LIKE '$tableName'");
                        if ($checkTable->rowCount() > 0) {
                            error_log("Table $tableName already exists, skipping creation");
                            continue;
                        }
                    }
                }

                // Handle INSERT statements
                if (stripos($statement, 'INSERT INTO') !== false) {
                    // Extract table name and columns
                    preg_match('/INSERT INTO\s+`?(\w+)`?\s*\(([^)]+)\)/i', $statement, $matches);
                    if (isset($matches[1], $matches[2])) {
                        $tableName = $matches[1];
                        $columns = array_map('trim', explode(',', $matches[2]));
                        
                        // Check if table exists
                        $checkTable = $db->query("SHOW TABLES LIKE '$tableName'");
                        if ($checkTable->rowCount() === 0) {
                            error_log("Table $tableName does not exist, skipping insert");
                            continue;
                        }
                        
                        // Check if all columns exist
                        $validColumns = true;
                        $tableColumns = $db->query("SHOW COLUMNS FROM `$tableName`")->fetchAll(PDO::FETCH_COLUMN);
                        
                        foreach ($columns as $column) {
                            $column = trim(str_replace('`', '', $column));
                            if (!in_array($column, $tableColumns)) {
                                error_log("Column $column does not exist in table $tableName, skipping insert");
                                $validColumns = false;
                                break;
                            }
                        }
                        
                        if (!$validColumns) {
                            continue;
                        }
                    }
                }

                // Handle ALTER TABLE statements
                if (stripos($statement, 'ALTER TABLE') !== false) {
                    // Extract table name
                    preg_match('/ALTER TABLE\s+`?(\w+)`?/i', $statement, $matches);
                    if (isset($matches[1])) {
                        $tableName = $matches[1];
                        
                        // Check if table exists
                        $checkTable = $db->query("SHOW TABLES LIKE '$tableName'");
                        if ($checkTable->rowCount() === 0) {
                            error_log("Table $tableName does not exist, skipping alter");
                            continue;
                        }

                        // Handle MODIFY for auto_increment
                        if (stripos($statement, 'MODIFY') !== false && stripos($statement, 'AUTO_INCREMENT') !== false) {
                            // Check if column is already auto_increment
                            preg_match('/MODIFY\s+`?(\w+)`?/i', $statement, $colMatches);
                            if (isset($colMatches[1])) {
                                $columnName = $colMatches[1];
                                $checkColumn = $db->query("SHOW COLUMNS FROM `$tableName` WHERE Field = '$columnName'");
                                $columnInfo = $checkColumn->fetch(PDO::FETCH_ASSOC);
                                if ($columnInfo && stripos($columnInfo['Extra'], 'auto_increment') !== false) {
                                    error_log("Column $columnName in $tableName is already auto_increment, skipping modify");
                                    continue;
                                }

                                // Check if column is a primary key
                                $checkPrimary = $db->query("SHOW KEYS FROM `$tableName` WHERE Key_name = 'PRIMARY' AND Column_name = '$columnName'");
                                if ($checkPrimary->rowCount() === 0) {
                                    // Add primary key if it doesn't exist
                                    try {
                                        $db->exec("ALTER TABLE `$tableName` ADD PRIMARY KEY (`$columnName`)");
                                        error_log("Added primary key on $columnName in $tableName");
                                    } catch (PDOException $e) {
                                        if ($e->getCode() !== '42000' || stripos($e->getMessage(), 'Duplicate') === false) {
                                            throw $e;
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Handle ADD operations (columns, indexes, etc.)
                        if (stripos($statement, 'ADD') !== false) {
                            if (stripos($statement, 'ADD UNIQUE KEY') !== false || 
                                stripos($statement, 'ADD INDEX') !== false || 
                                stripos($statement, 'ADD PRIMARY KEY') !== false) {
                                // Check if index exists
                                preg_match('/ADD\s+(?:UNIQUE\s+KEY|INDEX|PRIMARY\s+KEY)\s+`?(\w+)`?/i', $statement, $indexMatches);
                                if (isset($indexMatches[1])) {
                                    $indexName = $indexMatches[1];
                                    $checkIndex = $db->query("SHOW INDEX FROM `$tableName` WHERE Key_name = '$indexName'");
                                    if ($checkIndex->rowCount() > 0) {
                                        error_log("Index $indexName already exists in $tableName, skipping add");
                                        continue;
                                    }
                                }
                            } else {
                                // Check if column exists
                                preg_match('/ADD\s+(?:COLUMN\s+)?`?(\w+)`?/i', $statement, $colMatches);
                                if (isset($colMatches[1])) {
                                    $columnName = $colMatches[1];
                                    $checkColumn = $db->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
                                    if ($checkColumn->rowCount() > 0) {
                                        error_log("Column $columnName already exists in $tableName, skipping add");
                                        continue;
                                    }
                                }
                            }
                        }
                    }
                }

                // Handle foreign key constraints
                if (stripos($statement, 'FOREIGN KEY') !== false) {
                    preg_match('/CONSTRAINT\s+`?(\w+)`?\s+FOREIGN KEY\s+\(`?(\w+)`?\)\s+REFERENCES\s+`?(\w+)`?\s+\(`?(\w+)`?\)/i', $statement, $matches);
                    if (isset($matches[3], $matches[4])) {
                        $refTable = $matches[3];
                        $refColumn = $matches[4];
                        
                        // Check if referenced table exists
                        $checkRefTable = $db->query("SHOW TABLES LIKE '$refTable'");
                        if ($checkRefTable->rowCount() === 0) {
                            error_log("Referenced table $refTable does not exist, skipping foreign key creation");
                            continue;
                        }
                        
                        // Check if referenced column exists and is a key
                        $checkRefColumn = $db->query("SHOW KEYS FROM `$refTable` WHERE Column_name = '$refColumn'");
                        if ($checkRefColumn->rowCount() === 0) {
                            error_log("Referenced column $refColumn is not a key in table $refTable, skipping foreign key creation");
                            continue;
                        }
                    }
                }

                error_log("Executing statement: " . substr($statement, 0, 100) . "...");
                $db->exec($statement);
                
            } catch (PDOException $e) {
                $errorCode = $e->getCode();
                $errorInfo = $db->errorInfo();
                
                error_log("SQL Error in file " . basename($sqlFile) . ":");
                error_log("Error code: " . $errorCode);
                error_log("SQL State: " . $errorInfo[0]);
                error_log("Error message: " . $e->getMessage());
                
                // Handle specific error cases
                if (
                    $errorCode === '42S01' || // Table already exists
                    $errorCode === '42S21' || // Column already exists
                    $errorCode === '23000' || // Duplicate entry
                    ($errorCode === '42000' && stripos($e->getMessage(), 'Duplicate') !== false) // Duplicate key/index
                ) {
                    error_log("Ignorable error, continuing...");
                    continue;
                }
                
                throw $e;
            }
        }
        return true;
    } catch (PDOException $e) {
        error_log("SQL Error: " . $e->getMessage() . " in file: " . $sqlFile);
        return false;
    }
}

function generateProjectUrl($name) {
    $url = strtolower(str_replace(' ', '-', $name));
    $url = preg_replace('/[^a-z0-9-]/', '', $url);
    return $url;
}

function processProject($zipFile, $projectName, $databaseName) {
    try {
        // Create temp directory for initial extraction
        $tempExtractPath = "C:/xampp/temp/" . uniqid();
        mkdir($tempExtractPath, 0777, true);

        $zip = new ZipArchive();
        if ($zip->open($zipFile) === TRUE) {
            // Get the original folder name from the zip
            $originalFolderName = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (strpos($name, '/') !== false) {
                    $parts = explode('/', $name);
                    if (!empty($parts[0])) {
                        $originalFolderName = $parts[0];
                        break;
                    }
                }
            }

            $zip->extractTo($tempExtractPath);
            $zip->close();

            // Create database connection
            $db = new PDO("mysql:host=localhost", "root", "");
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create new database using user-provided name
            $db->exec("CREATE DATABASE IF NOT EXISTS `$databaseName`");
            $db->exec("USE `$databaseName`");

            error_log("Created new database: $databaseName");

            // Process SQL files
            $sqlFiles = findSqlFiles($tempExtractPath);
            $errors = [];
            $successfulFiles = [];

            foreach ($sqlFiles as $sqlFile) {
                error_log("Starting to process SQL file: " . basename($sqlFile));
                if (executeSqlFile($db, $sqlFile)) {
                    $successfulFiles[] = basename($sqlFile);
                } else {
                    $errors[] = "Failed to execute SQL file: " . basename($sqlFile);
                }
                error_log("Finished processing SQL file: " . basename($sqlFile));
            }

            // Switch to project_repo database for project record
            $db->exec("USE project_repo");

            // Insert project record
            $cleanProjectName = generateProjectUrl($projectName);
            $projectUrl = $cleanProjectName; // Remove /projects/ prefix as it's added by frontend
            $status = empty($errors) ? 'deployed' : 'failed';
            $errorLog = empty($errors) ? null : implode("\n", $errors);
            
            // Get user_id from session
            session_start();
            $userId = $_SESSION['user']['id'] ?? 1; // Default to 1 if not set

            // Check the structure of the extracted files
            $extractedContents = scandir($tempExtractPath);
            $mainFolder = null;
            
            // Remove . and .. from the list
            $extractedContents = array_diff($extractedContents, array('.', '..'));
            
            // If there's only one item and it's a directory, that's our main folder
            if (count($extractedContents) === 1) {
                $firstItem = array_values($extractedContents)[0];
                $fullPath = $tempExtractPath . '/' . $firstItem;
                if (is_dir($fullPath)) {
                    $mainFolder = $firstItem;
                }
            }

            // Deploy to projects directory using the clean project name
            $projectDir = "C:/xampp/htdocs/project-repo/projects/" . $cleanProjectName;
            if (!file_exists($projectDir)) {
                mkdir($projectDir, 0777, true);
            }
            
            // If we found a main folder, copy from there instead of the temp root
            if ($mainFolder) {
                $sourcePath = $tempExtractPath . '/' . $mainFolder;
                copyDirectory($sourcePath, $projectDir);
            } else {
                // Copy files from temp extraction to project directory
                copyDirectory($tempExtractPath, $projectDir);
            }

            // Detect the project type and setup accordingly
            $projectType = detectProjectType($projectDir);
            
            // Create .htaccess for URL rewriting based on project type
            $htaccess = $projectDir . '/.htaccess';
            $htaccessContent = "RewriteEngine On\nRewriteBase /project-repo/projects/$cleanProjectName/\n\n";
            
            switch ($projectType) {
                case 'laravel':
                    $htaccessContent .= "
                        # Handle Front Controller Pattern
                        RewriteCond %{REQUEST_FILENAME} !-d
                        RewriteCond %{REQUEST_FILENAME} !-f
                        RewriteRule ^ public/index.php [L]
                    ";
                    break;
                    
                case 'codeigniter':
                    $htaccessContent .= "
                        # Handle Front Controller Pattern
                        RewriteCond %{REQUEST_FILENAME} !-d
                        RewriteCond %{REQUEST_FILENAME} !-f
                        RewriteRule ^ index.php [L]
                    ";
                    break;
                    
                case 'wordpress':
                    $htaccessContent .= "
                        # Handle WordPress
                        RewriteCond %{REQUEST_FILENAME} !-f
                        RewriteCond %{REQUEST_FILENAME} !-d
                        RewriteRule . index.php [L]
                    ";
                    break;
                    
                default:
                    // For static sites or unknown frameworks
                    $htaccessContent .= "
                        # Serve files directly if they exist
                        RewriteCond %{REQUEST_FILENAME} !-d
                        RewriteCond %{REQUEST_FILENAME} !-f
                        RewriteRule ^ index.php [L]
                        
                        # Prevent directory listing
                        Options -Indexes
                    ";
                    
                    // If no index file exists, create a basic one
                    if (!file_exists($projectDir . '/index.php') && 
                        !file_exists($projectDir . '/index.html')) {
                        copy(__DIR__ . '/templates/default_index.php', $projectDir . '/index.php');
                    }
            }
            
            file_put_contents($htaccess, trim($htaccessContent));

            // Update config files with new database name if they exist
            $configFiles = [
                $projectDir . '/config/database.php',
                $projectDir . '/config.php',
                $projectDir . '/application/config/database.php',
                $projectDir . '/wp-config.php'
            ];

            foreach ($configFiles as $configFile) {
                if (file_exists($configFile)) {
                    $configContent = file_get_contents($configFile);
                    // Update database name
                    $configContent = preg_replace(
                        "/'database'\s*=>\s*'[^']*'/",
                        "'database' => '$databaseName'",
                        $configContent
                    );
                    // Update database credentials
                    $configContent = preg_replace(
                        "/'username'\s*=>\s*'[^']*'/",
                        "'username' => 'root'",
                        $configContent
                    );
                    $configContent = preg_replace(
                        "/'password'\s*=>\s*'[^']*'/",
                        "'password' => ''",
                        $configContent
                    );
                    $configContent = preg_replace(
                        "/'hostname'\s*=>\s*'[^']*'/",
                        "'hostname' => 'localhost'",
                        $configContent
                    );
                    file_put_contents($configFile, $configContent);
                }
            }

            // Create a project info file
            $infoFile = $projectDir . '/project-info.json';
            file_put_contents($infoFile, json_encode([
                'id' => $projectId,
                'name' => $projectName,
                'description' => $description,
                'database' => $databaseName,
                'url' => $projectUrl, // Frontend will prepend the full path
                'type' => $projectType,
                'created_at' => date('Y-m-d H:i:s'),
                'status' => $status
            ], JSON_PRETTY_PRINT));

            // Insert project record
            $stmt = $db->prepare("
                INSERT INTO projects 
                (user_id, name, description, database_name, upload_path, url, status, error_log) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $description = "Project database: $databaseName";
            
            $stmt->execute([
                $userId,
                $projectName,
                $description,
                $databaseName,
                $projectDir,
                $projectUrl,
                $status,
                $errorLog
            ]);
            
            $projectId = $db->lastInsertId();

            // Log deployment
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $stmt = $db->prepare("
                        INSERT INTO deployment_logs 
                        (project_id, log_message) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$projectId, $error]);
                }
            } else {
                $stmt = $db->prepare("
                    INSERT INTO deployment_logs 
                    (project_id, log_message) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$projectId, "Project deployed successfully"]);
            }

            // Clean up temp directory
            deleteDirectory($tempExtractPath);

            return [
                'success' => true,
                'message' => 'Project processed successfully',
                'data' => [
                    'projectId' => $projectId,
                    'status' => $status,
                    'url' => $projectUrl,
                    'errors' => $errors,
                    'successful_files' => $successfulFiles
                ]
            ];
        } else {
            throw new Exception("Failed to open ZIP file");
        }
    } catch (Exception $e) {
        error_log("Error processing project: " . $e->getMessage());
        if (isset($tempExtractPath) && file_exists($tempExtractPath)) {
            deleteDirectory($tempExtractPath);
        }
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

function copyDirectory($source, $destination) {
    if (!is_dir($destination)) {
        mkdir($destination, 0777, true);
    }
    
    $dir = opendir($source);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $srcFile = $source . '/' . $file;
            $destFile = $destination . '/' . $file;
            
            if (is_dir($srcFile)) {
                copyDirectory($srcFile, $destFile);
            } else {
                copy($srcFile, $destFile);
            }
        }
    }
    closedir($dir);
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }

    return rmdir($dir);
}

function detectProjectType($projectDir) {
    // Check for Laravel
    if (file_exists($projectDir . '/artisan') && 
        file_exists($projectDir . '/public/index.php')) {
        return 'laravel';
    }
    
    // Check for CodeIgniter
    if (file_exists($projectDir . '/application/config/config.php')) {
        return 'codeigniter';
    }
    
    // Check for WordPress
    if (file_exists($projectDir . '/wp-config.php') || 
        file_exists($projectDir . '/wp-config-sample.php')) {
        return 'wordpress';
    }
    
    // Default to static/basic PHP
    return 'basic';
}

try {
    // Debug logging
    error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));

    // Verify request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Method not allowed', [], 405);
    }

    // Validate required fields
    if (!isset($_FILES['zipFile']) || !isset($_POST['uploadId']) || !isset($_POST['userId']) || 
        !isset($_POST['name']) || !isset($_POST['database_name'])) {
        sendResponse(false, 'Missing required fields: zipFile, uploadId, userId, name, or database_name', [], 400);
    }

    // Get input data
    $uploadId = $_POST['uploadId'];
    $userId = $_POST['userId'];
    $projectName = $_POST['name'];
    $databaseName = $_POST['database_name'];
    $description = $_POST['description'] ?? '';

    // Validate database name
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $databaseName)) {
        sendResponse(false, 'Database name can only contain letters, numbers, and underscores', [], 400);
    }

    // Check if database already exists
    try {
        $tempDb = new PDO("mysql:host=localhost", "root", "");
        $stmt = $tempDb->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = " . 
            $tempDb->quote($databaseName));
        if ($stmt->fetch()) {
            sendResponse(false, "Database '$databaseName' already exists. Please choose a different name.", [], 400);
        }
    } catch (PDOException $e) {
        error_log("Error checking database existence: " . $e->getMessage());
        sendResponse(false, "Error validating database name", [], 500);
    }

    $zipFile = $_FILES['zipFile']['tmp_name'];

    $result = processProject($zipFile, $projectName, $databaseName);
    sendResponse($result['success'], $result['message'], $result['data']);

} catch (Exception $e) {
    error_log("Error in process.php: " . $e->getMessage());
    sendResponse(false, $e->getMessage(), [], 500);
}
