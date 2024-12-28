<?php
require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Add database_name column if it doesn't exist
    $sql = "ALTER TABLE projects 
            ADD COLUMN IF NOT EXISTS database_name VARCHAR(64) 
            AFTER description";
    
    $db->exec($sql);
    
    echo "Migration completed successfully: Added database_name column to projects table\n";
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
