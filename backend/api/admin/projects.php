<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';

class ProjectController {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getProjects() {
        try {
            $query = "SELECT * FROM projects ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            $projects = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                array_push($projects, $row);
            }

            return json_encode([
                "status" => "success",
                "data" => $projects
            ]);
        } catch(PDOException $e) {
            return json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]);
        }
    }

    public function deleteProject($id) {
        try {
            // First get the project to delete its files
            $query = "SELECT upload_path FROM projects WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$id]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($project) {
                // Delete project files
                $this->deleteProjectFiles($project['upload_path']);

                // Delete from database
                $query = "DELETE FROM projects WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$id]);

                if ($stmt->rowCount() > 0) {
                    return json_encode([
                        "status" => "success",
                        "message" => "Project deleted successfully"
                    ]);
                }
            }

            return json_encode([
                "status" => "error",
                "message" => "Project not found"
            ]);
        } catch(PDOException $e) {
            return json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]);
        }
    }

    private function deleteProjectFiles($path) {
        if (is_dir($path)) {
            $files = array_diff(scandir($path), array('.', '..'));
            foreach ($files as $file) {
                $filePath = $path . '/' . $file;
                if (is_dir($filePath)) {
                    $this->deleteProjectFiles($filePath);
                } else {
                    unlink($filePath);
                }
            }
            rmdir($path);
        }
    }
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Create project controller instance
$projectController = new ProjectController($db);

// Handle request
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    echo $projectController->getProjects();
} elseif ($method === 'DELETE') {
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    if ($id) {
        echo $projectController->deleteProject($id);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Project ID is required"
        ]);
    }
}
