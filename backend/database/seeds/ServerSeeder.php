<?php
/**
 * SERVER SEEDER
 * 
 * Tạo server mẫu để demo
 * 
 * @package Seeds
 */

use Core\Database;

class ServerSeeder
{
    public function run()
    {
        $db = Database::getInstance();
        
        // Xóa dữ liệu cũ
        $db->query("TRUNCATE TABLE servers");
        
        $servers = [
            [
                'name' => 'Web Server Production',
                'ip_address' => '192.168.1.10',
                'hostname' => 'web01.prod.local',
                'os' => 'Ubuntu 22.04 LTS',
                'environment' => 'production',
                'status' => 'active',
                'ssh_port' => 22,
                'ssh_username' => 'admin',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Database Server',
                'ip_address' => '192.168.1.20',
                'hostname' => 'db01.prod.local',
                'os' => 'Ubuntu 22.04 LTS',
                'environment' => 'production',
                'status' => 'active',
                'ssh_port' => 22,
                'ssh_username' => 'admin',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Application Server',
                'ip_address' => '192.168.1.30',
                'hostname' => 'app01.prod.local',
                'os' => 'Ubuntu 22.04 LTS',
                'environment' => 'production',
                'status' => 'active',
                'ssh_port' => 22,
                'ssh_username' => 'admin',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Staging Server',
                'ip_address' => '192.168.2.10',
                'hostname' => 'web01.staging.local',
                'os' => '