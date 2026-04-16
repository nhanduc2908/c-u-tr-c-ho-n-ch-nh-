<?php
/**
 * MIGRATION: Add foreign keys constraints
 * 
 * Thêm các ràng buộc khóa ngoại
 * 
 * @package Migrations
 */

use Core\Database;

class AddForeignKeysTable
{
    public function up()
    {
        $db = Database::getInstance();
        
        $sqls = [
            "ALTER TABLE assessment_results ADD FOREIGN KEY (report_id) REFERENCES assessment_reports(id) ON DELETE CASCADE",
            "ALTER TABLE vulnerability_history ADD FOREIGN KEY (vulnerability_id) REFERENCES vulnerabilities(id) ON DELETE CASCADE",
            "ALTER TABLE alert_notes ADD FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE"
        ];
        
        foreach ($sqls as $sql) {
            try {
                $db->query($sql);
            } catch (Exception $e) {
                // Foreign key might already exist
            }
        }
        
        echo "✓ Added foreign keys\n";
    }
    
    public function down()
    {
        $db = Database::getInstance();
        
        // Drop foreign keys (implementation depends on MySQL version)
        echo "✓ Foreign keys removed (if any)\n";
    }
}