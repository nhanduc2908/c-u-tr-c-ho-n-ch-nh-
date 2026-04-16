<?php
/**
 * MIGRATION: Create user_server table
 * 
 * Bảng liên kết user và server (cho viewer)
 * 
 * @package Migrations
 */

use Core\Database;

class CreateUserServerTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sql = "CREATE TABLE IF NOT EXISTS user_server (
            user_id INT NOT NULL,
            server_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, server_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng liên kết user-server'";
        
        $db->query($sql);
        
        echo "✓ Created user_server table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        $db->query("DROP TABLE IF EXISTS user_server");
        echo "✓ Dropped user_server table\n";
    }
}