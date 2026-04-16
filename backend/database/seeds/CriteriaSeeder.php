<?php
/**
 * USER SEEDER
 * 
 * Tạo 3 user mặc định: admin, security_officer, viewer
 * Mật khẩu mặc định: 123456
 * 
 * @package Seeds
 */

use Core\Database;

class UserSeeder
{
    public function run()
    {
        $db = Database::getInstance();
        
        // Xóa dữ liệu cũ
        $db->query("TRUNCATE TABLE users");
        
        // Mật khẩu mã hóa cho '123456'
        $defaultPasswordHash = password_hash('123456', PASSWORD_DEFAULT);
        
        // Tạo 3 user
        $users = [
            [
                'username' => 'admin',
                'email' => 'admin@security.com',
                'password_hash' => $defaultPasswordHash,
                'full_name' => 'Administrator',
                'role_id' => 1,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'username' => 'security_officer',
                'email' => 'officer@security.com',
                'password_hash' => $defaultPasswordHash,
                'full_name' => 'Security Officer',
                'role_id' => 2,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'username' => 'viewer',
                'email' => 'viewer@security.com',
                'password_hash' => $defaultPasswordHash,
                'full_name' => 'Viewer User',
                'role_id' => 3,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        foreach ($users as $user) {
            $db->insert('users', $user);
        }
        
        echo "      - Created 3 users: admin, security_officer, viewer\n";
        echo "      - Default password: 123456\n";
    }
}