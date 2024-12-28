<?php
// Disable error display
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

header("Access-Control-Allow-Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, Cookie");
header("Access-Control-Allow-Credentials: true");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../../config/database.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    error_log('User not logged in. Session: ' . print_r($_SESSION, true));
    error_log('Cookies: ' . print_r($_COOKIE, true));
    http_response_code(401);
    echo json_encode(array("message" => "Unauthorized"));
    exit();
}

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$project_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$project_id) {
    http_response_code(400);
    echo json_encode(array("message" => "Project ID is required"));
    exit();
}

switch ($method) {
    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        
        // Verify project belongs to user
        $check = $db->prepare("SELECT id FROM projects WHERE id = :id AND user_id = :user_id");
        $check->execute([
            ':id' => $project_id,
            ':user_id' => $_SESSION['user']['id']
        ]);
        
        if ($check->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(array("message" => "Not authorized to modify this project"));
            exit();
        }
        
        try {
            $updates = array();
            $params = array();
            $needsFolderRename = false;
            $needsDatabaseRename = false;
            $oldProjectData = null;
            
            // Get current project data
            $stmt = $db->prepare("SELECT * FROM projects WHERE id = :id");
            $stmt->execute([':id' => $project_id]);
            $oldProjectData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldProjectData) {
                http_response_code(404);
                echo json_encode(array("message" => "Project not found"));
                exit();
            }
            
            if (!empty($data->name) && $data->name !== $oldProjectData['name']) {
                $updates[] = "name = :name";
                $params[':name'] = $data->name;
                $needsFolderRename = true;
                
                // Generate new URL from name
                $newUrl = preg_replace('/[^a-zA-Z0-9-]/', '-', strtolower($data->name));
                $updates[] = "url = :url";
                $params[':url'] = $newUrl;
            }
            
            if (isset($data->description)) {
                $updates[] = "description = :description";
                $params[':description'] = $data->description;
            }

            if (isset($data->database_name) && $data->database_name !== $oldProjectData['database_name']) {
                // Validate database name (only alphanumeric and underscores)
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $data->database_name)) {
                    http_response_code(400);
                    echo json_encode(array("message" => "Database name can only contain letters, numbers, and underscores"));
                    exit();
                }
                
                // Check if new database name already exists
                try {
                    $tempDb = new PDO("mysql:host=localhost", "root", "");
                    $checkStmt = $tempDb->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = " . 
                        $tempDb->quote($data->database_name));
                    if ($checkStmt->fetch()) {
                        http_response_code(400);
                        echo json_encode(array("message" => "Database '$data->database_name' already exists. Please choose a different name."));
                        exit();
                    }
                } catch (PDOException $e) {
                    error_log("Error checking database existence: " . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(array("message" => "Error validating database name"));
                    exit();
                }
                
                $updates[] = "database_name = :database_name";
                $params[':database_name'] = $data->database_name;
                $needsDatabaseRename = true;
            }
            
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(array("message" => "No valid updates provided"));
                exit();
            }
            
            $params[':id'] = $project_id;
            $query = "UPDATE projects SET " . implode(", ", $updates) . " WHERE id = :id";
            
            $db->beginTransaction();
            
            try {
                // Update project record
                $stmt = $db->prepare($query);
                if ($stmt->execute($params)) {
                    // Handle folder rename if needed
                    if ($needsFolderRename) {
                        $oldPath = $oldProjectData['upload_path'];
                        $newPath = "C:/xampp/htdocs/project-repo/projects/" . $params[':url'];
                        
                        // Make sure target directory doesn't exist
                        if (file_exists($newPath)) {
                            throw new Exception("A project with this name already exists");
                        }
                        
                        // Create parent directory if it doesn't exist
                        $parentDir = dirname($newPath);
                        if (!file_exists($parentDir)) {
                            mkdir($parentDir, 0777, true);
                        }
                        
                        // Ensure source exists and is readable
                        if (!file_exists($oldPath)) {
                            throw new Exception("Source project directory not found");
                        }
                        
                        if (!is_readable($oldPath)) {
                            throw new Exception("Source project directory is not readable");
                        }
                        
                        // Attempt to rename
                        if (!rename($oldPath, $newPath)) {
                            // If rename fails, try copy and delete
                            if (!copyDirectory($oldPath, $newPath)) {
                                throw new Exception("Failed to copy project directory");
                            }
                            if (!deleteDirectory($oldPath)) {
                                // If delete fails, rollback the copy
                                deleteDirectory($newPath);
                                throw new Exception("Failed to remove old project directory");
                            }
                        }
                        
                        // Update the upload_path in database
                        $pathUpdate = $db->prepare("UPDATE projects SET upload_path = :path WHERE id = :id");
                        $pathUpdate->execute([':path' => $newPath, ':id' => $project_id]);
                        
                        // Update project info file
                        $infoFile = $newPath . '/project-info.json';
                        if (file_exists($infoFile)) {
                            $projectInfo = json_decode(file_get_contents($infoFile), true);
                            $projectInfo['name'] = $data->name;
                            $projectInfo['url'] = $params[':url'];
                            file_put_contents($infoFile, json_encode($projectInfo, JSON_PRETTY_PRINT));
                        }
                    }
                    
                    // Handle database rename if needed
                    if ($needsDatabaseRename) {
                        try {
                            $rootDb = new PDO("mysql:host=localhost", "root", "");
                            $rootDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            
                            // Create new database
                            $rootDb->exec("CREATE DATABASE IF NOT EXISTS `{$data->database_name}`");
                            
                            // Get all tables from old database
                            $oldDb = new PDO("mysql:host=localhost;dbname={$oldProjectData['database_name']}", "root", "");
                            $oldDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            
                            // Get all tables and their create statements
                            $tables = [];
                            $tableQuery = $oldDb->query("SHOW TABLES");
                            while ($row = $tableQuery->fetch(PDO::FETCH_NUM)) {
                                $tableName = $row[0];
                                $createStmt = $oldDb->query("SHOW CREATE TABLE `$tableName`")->fetch(PDO::FETCH_ASSOC);
                                $tables[$tableName] = [
                                    'name' => $tableName,
                                    'create' => $createStmt['Create Table'],
                                    'hasForeignKeys' => (strpos($createStmt['Create Table'], 'FOREIGN KEY') !== false)
                                ];
                            }
                            
                            // Sort tables - tables without foreign keys first
                            uasort($tables, function($a, $b) {
                                if ($a['hasForeignKeys'] && !$b['hasForeignKeys']) return 1;
                                if (!$a['hasForeignKeys'] && $b['hasForeignKeys']) return -1;
                                return 0;
                            });
                            
                            // Disable foreign key checks temporarily
                            $rootDb->exec("USE `{$data->database_name}`");
                            $rootDb->exec("SET FOREIGN_KEY_CHECKS=0");
                            
                            try {
                                // Create tables and copy data
                                foreach ($tables as $table) {
                                    // Create table
                                    $rootDb->exec($table['create']);
                                    
                                    // Copy data
                                    $data = $oldDb->query("SELECT * FROM `{$table['name']}`")->fetchAll(PDO::FETCH_ASSOC);
                                    if (!empty($data)) {
                                        $columns = array_keys($data[0]);
                                        $columnList = '`' . implode('`, `', $columns) . '`';
                                        $valuePlaceholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
                                        
                                        $insertSql = "INSERT INTO `{$table['name']}` ($columnList) VALUES $valuePlaceholders";
                                        $stmt = $rootDb->prepare($insertSql);
                                        
                                        foreach ($data as $row) {
                                            $stmt->execute(array_values($row));
                                        }
                                    }
                                }
                                
                                // Re-enable foreign key checks
                                $rootDb->exec("SET FOREIGN_KEY_CHECKS=1");
                                
                                // Verify foreign key constraints
                                foreach ($tables as $table) {
                                    if ($table['hasForeignKeys']) {
                                        $result = $rootDb->query("CHECK TABLE `{$table['name']}`");
                                        $status = $result->fetch(PDO::FETCH_ASSOC);
                                        if ($status['Msg_text'] !== 'OK') {
                                            throw new Exception("Foreign key verification failed for table {$table['name']}");
                                        }
                                    }
                                }
                                
                                // Update database references in the new database
                                $tablesWithDbRefs = ['config', 'settings', 'connections', 'databases'];
                                
                                foreach ($tablesWithDbRefs as $table) {
                                    // Check if table exists
                                    $tableExists = $rootDb->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
                                    if (!$tableExists) continue;
                                    
                                    // Get all columns
                                    $columns = $rootDb->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_COLUMN);
                                    
                                    // Look for columns that might contain database references
                                    $potentialColumns = array_filter($columns, function($col) {
                                        return stripos($col, 'database') !== false 
                                            || stripos($col, 'db') !== false 
                                            || stripos($col, 'connection') !== false;
                                    });
                                    
                                    foreach ($potentialColumns as $column) {
                                        $rootDb->exec("UPDATE `$table` SET `$column` = '{$data->database_name}' 
                                                     WHERE `$column` = '{$oldProjectData['database_name']}'");
                                    }
                                }
                                
                                // Update database references in configuration files
                                $configFiles = glob($newPath . '/**/*.{php,json,yml,yaml,xml,config}', GLOB_BRACE);
                                foreach ($configFiles as $file) {
                                    $content = file_get_contents($file);
                                    $content = str_replace(
                                        $oldProjectData['database_name'],
                                        $data->database_name,
                                        $content
                                    );
                                    file_put_contents($file, $content);
                                }
                                
                                // Drop old database
                                $rootDb->exec("DROP DATABASE IF EXISTS `{$oldProjectData['database_name']}`");
                                
                                // Update project info file
                                $infoFile = $oldProjectData['upload_path'] . '/project-info.json';
                                if (file_exists($infoFile)) {
                                    $projectInfo = json_decode(file_get_contents($infoFile), true);
                                    $projectInfo['database'] = $data->database_name;
                                    file_put_contents($infoFile, json_encode($projectInfo, JSON_PRETTY_PRINT));
                                }
                            } finally {
                                // Always re-enable foreign key checks
                                $rootDb->exec("SET FOREIGN_KEY_CHECKS=1");
                            }
                        } catch (Exception $e) {
                            // Clean up on error
                            $rootDb->exec("DROP DATABASE IF EXISTS `{$data->database_name}`");
                            throw new Exception("Failed to rename database: " . $e->getMessage());
                        }
                    }
                    
                    $db->commit();
                    
                    // Get updated project
                    $query = "SELECT * FROM projects WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->execute([':id' => $project_id]);
                    $project = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    http_response_code(200);
                    echo json_encode(array(
                        "message" => "Project updated successfully",
                        "project" => $project
                    ));
                    exit();
                } else {
                    throw new Exception("Failed to update project record");
                }
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error in manage.php: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(array("message" => "Failed to update project: " . $e->getMessage()));
                exit();
            }
        } catch (Exception $e) {
            error_log("Error in manage.php: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(array("message" => "Failed to update project: " . $e->getMessage()));
            exit();
        }
        break;

    case 'DELETE':
        try {
            // Get project details first
            $check = $db->prepare("SELECT * FROM projects WHERE id = :id AND user_id = :user_id");
            $check->execute([
                ':id' => $project_id,
                ':user_id' => $_SESSION['user']['id']
            ]);
            
            if ($check->rowCount() === 0) {
                http_response_code(403);
                echo json_encode(array("message" => "Not authorized to delete this project"));
                exit();
            }
            
            $project = $check->fetch(PDO::FETCH_ASSOC);
            
            // Try to drop the project's database if it exists
            try {
                $rootDb = new PDO("mysql:host=localhost", "root", "");
                $rootDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $rootDb->exec("DROP DATABASE IF EXISTS `{$project['database_name']}`");
                error_log("Dropped database: " . $project['database_name']);
            } catch (PDOException $e) {
                error_log("Error dropping database {$project['database_name']}: " . $e->getMessage());
                // Continue with deletion even if database drop fails
            }
            
            // Delete project files
            if (!empty($project['upload_path']) && file_exists($project['upload_path'])) {
                try {
                    // Recursively delete project directory
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($project['upload_path'], RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CHILD_FIRST
                    );
                    
                    foreach ($files as $file) {
                        if ($file->isDir()) {
                            rmdir($file->getRealPath());
                        } else {
                            unlink($file->getRealPath());
                        }
                    }
                    rmdir($project['upload_path']);
                } catch (Exception $e) {
                    error_log("Error deleting project files: " . $e->getMessage());
                    // Continue with deletion even if file deletion fails
                }
            }
            
            // Start transaction only for the project deletion
            $db->beginTransaction();
            
            // Delete from projects table
            $stmt = $db->prepare("DELETE FROM projects WHERE id = :id");
            if ($stmt->execute([':id' => $project_id])) {
                $db->commit();
                http_response_code(200);
                echo json_encode(array(
                    "message" => "Project and associated database deleted successfully"
                ));
            } else {
                throw new Exception("Failed to delete project from database");
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error in project deletion: " . $e->getMessage());
            http_response_code(503);
            echo json_encode(array("message" => "Error deleting project: " . $e->getMessage()));
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method not allowed"));
        break;
}

function copyDirectory($source, $destination) {
    if (!is_dir($destination)) {
        mkdir($destination, 0777, true);
    }
    
    foreach (scandir($source) as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $sourcePath = $source . '/' . $item;
        $destinationPath = $destination . '/' . $item;
        
        if (is_dir($sourcePath)) {
            if (!copyDirectory($sourcePath, $destinationPath)) {
                return false;
            }
        } else {
            if (!copy($sourcePath, $destinationPath)) {
                return false;
            }
        }
    }
    
    return true;
}

function deleteDirectory($directory) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    
    return rmdir($directory);
}
