<?php
/**
 * MIGRATION: Add indexes to users table
 * 
 * Thêm index để tối ưu truy vấn
 * 
 * @package Migrations
 */

use Core\Database;

class AddIndexesToUsersTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sqls = [
            "ALTER TABLE users ADD INDEX idx_username_email (username, email)",
            "ALTER TABLE users ADD INDEX idx_created_at (created_at)",
            "ALTER TABLE users ADD INDEX idx_last_login (last_login)"
        ];
        
        foreach ($sqls as $sql) {
            try {
                $db->query($sql);
            } catch (Exception $e) {
                // Index might already exist
            }
        }
        
        echo "✓ Added indexes to users table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        
        $sqls = [
            "ALTER TABLE users DROP INDEX idx_username_email",
            "ALTER TABLE users DROP INDEX idx_created_at",
            "ALTER TABLE users DROP INDEX idx_last_login"
        ];
        
        foreach ($sqls as $sql) {
            try {
                $db->query($sql);
            } catch (Exception $e) {
                // Index might not exist
            }
        }
        
        echo "✓ Removed indexes from users table\n";
    }
}
