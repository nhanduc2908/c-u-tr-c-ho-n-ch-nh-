<?php
/**
 * DATABASE SEEDER
 * 
 * Seeder chính - Chạy tất cả các seeder
 * 
 * @package Seeds
 */

use Core\Database;

class DatabaseSeeder
{
    /**
     * Run all seeders
     */
    public function run()
    {
        echo "\n🌱 Starting database seeding...\n";
        echo "========================================\n\n";
        
        // Chạy theo thứ tự (quan trọng: role trước, user sau)
        $seeders = [
            'RoleSeeder' => 'Seeding roles...',
            'PermissionSeeder' => 'Seeding permissions...',
            'CategorySeeder' => 'Seeding 17 categories...',
            'CriteriaSeeder' => 'Seeding 280 criteria...',
            'UserSeeder' => 'Seeding users...',
            'ServerSeeder' => 'Seeding servers...',
            'SettingSeeder' => 'Seeding settings...',
            'AssessmentSeeder' => 'Seeding assessments...'
        ];
        
        foreach ($seeders as $seeder => $message) {
            echo "📌 {$message}\n";
            
            $seederClass = "Seeds\\{$seeder}";
            if (class_exists($seederClass)) {
                $instance = new $seederClass();
                $instance->run();
                echo "   ✅ {$seeder} completed\n\n";
            } else {
                echo "   ⚠️ {$seeder} not found\n\n";
            }
        }
        
        echo "========================================\n";
        echo "✅ Database seeding completed!\n\n";
    }
}