<?php
/**
 * SSH CONNECTION SERVICE
 * 
 * Quản lý kết nối SSH đến server
 * - Kết nối SSH
 * - Thực thi lệnh
 * - Upload/download file
 * 
 * @package Services
 */

namespace Services;

use Core\Logger;

class SshConnectionService
{
    private $connection;
    private $isConnected = false;
    private $lastError = null;
    
    /**
     * Kết nối SSH đến server
     * 
     * @param string $host IP hoặc hostname
     * @param int $port Cổng SSH (mặc định 22)
     * @param string $username Tên đăng nhập
     * @param string $password Mật khẩu (hoặc null nếu dùng key)
     * @param string $privateKey Đường dẫn private key (optional)
     * @return bool
     */
    public function connect($host, $port = 22, $username, $password = null, $privateKey = null)
    {
        if (!function_exists('ssh2_connect')) {
            $this->lastError = 'SSH2 extension not installed';
            Logger::error('SSH2 extension not installed');
            return false;
        }
        
        try {
            $this->connection = ssh2_connect($host, $port);
            
            if (!$this->connection) {
                $this->lastError = "Cannot connect to {$host}:{$port}";
                return false;
            }
            
            // Xác thực
            if ($privateKey && file_exists($privateKey)) {
                $auth = ssh2_auth_pubkey_file(
                    $this->connection,
                    $username,
                    $privateKey . '.pub',
                    $privateKey,
                    $password
                );
            } elseif ($password) {
                $auth = ssh2_auth_password($this->connection, $username, $password);
            } else {
                $auth = false;
            }
            
            if (!$auth) {
                $this->lastError = "Authentication failed for {$username}@{$host}";
                return false;
            }
            
            $this->isConnected = true;
            Logger::info("SSH connected", ['host' => $host, 'username' => $username]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            Logger::error("SSH connection failed", ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Thực thi lệnh trên server
     * 
     * @param string $command Lệnh cần thực thi
     * @return string Output của lệnh
     */
    public function exec($command)
    {
        if (!$this->isConnected || !$this->connection) {
            $this->lastError = 'Not connected to SSH server';
            return '';
        }
        
        try {
            $stream = ssh2_exec($this->connection, $command);
            
            if (!$stream) {
                $this->lastError = "Failed to execute command: {$command}";
                return '';
            }
            
            stream_set_blocking($stream, true);
            $output = stream_get_contents($stream);
            fclose($stream);
            
            return trim($output);
            
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            Logger::error("SSH command failed", ['command' => $command, 'error' => $e->getMessage()]);
            return '';
        }
    }
    
    /**
     * Thực thi lệnh và lấy cả stdout và stderr
     * 
     * @param string $command Lệnh cần thực thi
     * @return array ['stdout' => string, 'stderr' => string, 'exit_code' => int]
     */
    public function execWithError($command)
    {
        if (!$this->isConnected || !$this->connection) {
            return ['stdout' => '', 'stderr' => 'Not connected', 'exit_code' => -1];
        }
        
        try {
            $stream = ssh2_exec($this->connection, $command);
            $errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
            
            stream_set_blocking($stream, true);
            stream_set_blocking($errorStream, true);
            
            $stdout = stream_get_contents($stream);
            $stderr = stream_get_contents($errorStream);
            
            fclose($stream);
            fclose($errorStream);
            
            return [
                'stdout' => trim($stdout),
                'stderr' => trim($stderr),
                'exit_code' => 0
            ];
            
        } catch (\Exception $e) {
            return [
                'stdout' => '',
                'stderr' => $e->getMessage(),
                'exit_code' => -1
            ];
        }
    }
    
    /**
     * Upload file lên server
     * 
     * @param string $localPath Đường dẫn file local
     * @param string $remotePath Đường dẫn file trên server
     * @return bool
     */
    public function upload($localPath, $remotePath)
    {
        if (!$this->isConnected || !$this->connection) {
            $this->lastError = 'Not connected to SSH server';
            return false;
        }
        
        if (!file_exists($localPath)) {
            $this->lastError = "Local file not found: {$localPath}";
            return false;
        }
        
        try {
            $sftp = ssh2_sftp($this->connection);
            $remoteFile = "ssh2.sftp://{$sftp}{$remotePath}";
            
            $data = file_get_contents($localPath);
            file_put_contents($remoteFile, $data);
            
            Logger::info("File uploaded via SSH", ['local' => $localPath, 'remote' => $remotePath]);
            return true;
            
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            Logger::error("SSH upload failed", ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Download file từ server
     * 
     * @param string $remotePath Đường dẫn file trên server
     * @param string $localPath Đường dẫn file local
     * @return bool
     */
    public function download($remotePath, $localPath)
    {
        if (!$this->isConnected || !$this->connection) {
            $this->lastError = 'Not connected to SSH server';
            return false;
        }
        
        try {
            $sftp = ssh2_sftp($this->connection);
            $remoteFile = "ssh2.sftp://{$sftp}{$remotePath}";
            
            $data = file_get_contents($remoteFile);
            file_put_contents($localPath, $data);
            
            Logger::info("File downloaded via SSH", ['remote' => $remotePath, 'local' => $localPath]);
            return true;
            
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            Logger::error("SSH download failed", ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Kiểm tra file tồn tại trên server
     * 
     * @param string $remotePath Đường dẫn file
     * @return bool
     */
    public function fileExists($remotePath)
    {
        if (!$this->isConnected || !$this->connection) {
            return false;
        }
        
        try {
            $sftp = ssh2_sftp($this->connection);
            $remoteFile = "ssh2.sftp://{$sftp}{$remotePath}";
            return file_exists($remoteFile);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Đọc file trên server
     * 
     * @param string $remotePath Đường dẫn file
     * @return string|null
     */
    public function readFile($remotePath)
    {
        if (!$this->isConnected || !$this->connection) {
            return null;
        }
        
        try {
            $sftp = ssh2_sftp($this->connection);
            $remoteFile = "ssh2.sftp://{$sftp}{$remotePath}";
            return file_get_contents($remoteFile);
        } catch (\Exception $e) {
            Logger::error("SSH read file failed", ['path' => $remotePath, 'error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Ghi file lên server
     * 
     * @param string $remotePath Đường dẫn file
     * @param string $content Nội dung file
     * @return bool
     */
    public function writeFile($remotePath, $content)
    {
        if (!$this->isConnected || !$this->connection) {
            return false;
        }
        
        try {
            $sftp = ssh2_sftp($this->connection);
            $remoteFile = "ssh2.sftp://{$sftp}{$remotePath}";
            file_put_contents($remoteFile, $content);
            return true;
        } catch (\Exception $e) {
            Logger::error("SSH write file failed", ['path' => $remotePath, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Kiểm tra kết nối (test)
     * 
     * @param string $host IP
     * @param int $port Cổng
     * @param string $username Tên đăng nhập
     * @return bool
     */
    public function testConnection($host, $port = 22, $username = null)
    {
        try {
            $connection = @ssh2_connect($host, $port);
            if (!$connection) {
                return false;
            }
            
            if ($username) {
                // Chỉ test kết nối, không cần auth
                return true;
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Đóng kết nối
     */
    public function disconnect()
    {
        if ($this->connection) {
            // SSH2 không có hàm disconnect cụ thể
            $this->connection = null;
        }
        $this->isConnected = false;
    }
    
    /**
     * Lấy lỗi cuối cùng
     */
    public function getLastError()
    {
        return $this->lastError;
    }
    
    /**
     * Kiểm tra trạng thái kết nối
     */
    public function isConnected()
    {
        return $this->isConnected;
    }
    
    public function __destruct()
    {
        $this->disconnect();
    }
}