<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Get PUT data
        $data = json_decode(file_get_contents('php://input'), true);
        $projectId = isset($data['project_id']) ? (int)$data['project_id'] : null;
        $path = isset($data['path']) ? trim($data['path']) : '';
        $content = isset($data['content']) ? $data['content'] : null;

        if (!$projectId || !$path || $content === null) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Project ID, path, and content are required'
            ]);
            exit();
        }

        // Initialize Database
        $database = new Database();
        $db = $database->getConnection();

        // Verify project exists and get its upload path
        $stmt = $db->prepare('SELECT id, upload_path FROM projects WHERE id = ?');
        $stmt->execute([$projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Project not found']);
            exit();
        }

        $fullPath = $project['upload_path'] . '/' . ltrim($path, '/');

        if (!file_exists($fullPath)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File not found']);
            exit();
        }

        if (!is_writable($fullPath)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'File is not writable']);
            exit();
        }

        if (file_put_contents($fullPath, $content) === false) {
            throw new Exception('Failed to write file');
        }

        echo json_encode([
            'success' => true,
            'message' => 'File updated successfully',
            'data' => [
                'name' => basename($fullPath),
                'path' => $path,
                'type' => 'file',
                'size' => filesize($fullPath),
                'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
            ]
        ]);
    } else {
        // Get request data
        $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
        $path = isset($_GET['path']) ? trim($_GET['path']) : '';

        if (!$projectId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Project ID is required']);
            exit();
        }

        // Initialize Database
        $database = new Database();
        $db = $database->getConnection();

        // Verify project exists and get its upload path
        $stmt = $db->prepare('SELECT id, upload_path FROM projects WHERE id = ?');
        $stmt->execute([$projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Project not found']);
            exit();
        }

        $uploadPath = $project['upload_path'];
        $fullPath = $uploadPath . ($path ? '/' . ltrim($path, '/') : '');

        if (!file_exists($fullPath)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Path not found']);
            exit();
        }

        $result = [];
        if (is_dir($fullPath)) {
            $items = scandir($fullPath);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                
                $itemPath = $fullPath . '/' . $item;
                $result[] = [
                    'name' => $item,
                    'path' => $path ? $path . '/' . $item : $item,
                    'type' => is_dir($itemPath) ? 'directory' : 'file',
                    'size' => is_file($itemPath) ? filesize($itemPath) : null,
                    'modified' => date('Y-m-d H:i:s', filemtime($itemPath))
                ];
            }
        } else {
            $result = [
                'name' => basename($fullPath),
                'path' => $path,
                'type' => 'file',
                'size' => filesize($fullPath),
                'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
                'content' => file_get_contents($fullPath)
            ];
        }

        echo json_encode(['success' => true, 'data' => $result]);
    }
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('Server error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
