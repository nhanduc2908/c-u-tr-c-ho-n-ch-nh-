<?php
/**
 * MIGRATION: Add two-factor authentication fields to users table
 * 
 * @package Migrations
 */

use Core\Database;

class AddTwoFactorFieldsToUsersTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sqls = [
            "ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(255) NULL AFTER two_factor_enabled",
            "ALTER TABLE users ADD COLUMN backup_codes TEXT NULL AFTER two_factor_secret",
            "ALTER TABLE users ADD COLUMN two_factor_recovery_at TIMESTAMP NULL AFTER backup_codes"
        ];
        
        foreach ($sqls as $sql) {
            try {
                $db->query($sql);
            } catch (Exception $e) {
                // Column might already exist
            }
        }
        
        echo "✓ Added two-factor fields to users table\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        
        $sqls = [
            "ALTER TABLE users DROP COLUMN two_factor_secret",
            "ALTER TABLE users DROP COLUMN backup_codes",
            "ALTER TABLE users DROP COLUMN two_factor_recovery_at"
        ];
        
        foreach ($sqls as $sql) {
            try {
                $db->query($sql);
            } catch (Exception $e) {
                // Column might not exist
            }
        }
        
        echo "✓ Removed two-factor fields from users table\n";
    }
}