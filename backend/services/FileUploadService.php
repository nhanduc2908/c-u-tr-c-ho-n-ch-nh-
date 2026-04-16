<?php
/**
 * FILE UPLOAD SERVICE
 * 
 * Xử lý upload file an toàn
 * - Kiểm tra loại file
 * - Kiểm tra kích thước
 - Xử lý ảnh (resize, crop)
 * - Bảo vệ chống mã độc
 * 
 * @package Services
 */

namespace Services;

use Core\Logger;

class FileUploadService
{
    private $uploadPath;
    private $allowedTypes;
    private $maxSize;
    private $allowedExtensions;
    
    public function __construct()
    {
        $this->uploadPath = __DIR__ . '/../storage/uploads/';
        $this->maxSize = $_ENV['UPLOAD_MAX_SIZE'] ?? 5242880; // 5MB
        $this->allowedExtensions = explode(',', $_ENV['UPLOAD_ALLOWED_EXTENSIONS'] ?? 'jpg,jpeg,png,gif,pdf,doc,docx,xlsx');
        $this->allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        
        // Tạo thư mục nếu chưa tồn tại
        $this->ensureDirectoryExists($this->uploadPath);
    }
    
    /**
     * Upload file
     * 
     * @param array $file $_FILES['file']
     * @param string $subDir Thư mục con (evidence, reports, avatars)
     * @param array $options Tùy chọn (resize, thumbnail, etc)
     * @return array Kết quả upload
     */
    public function upload($file, $subDir = 'general', $options = [])
    {
        // Kiểm tra lỗi upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->error($this->getUploadErrorMessage($file['error']));
        }
        
        // Kiểm tra kích thước
        if ($file['size'] > $this->maxSize) {
            return $this->error("File too large. Max size: " . $this->formatBytes($this->maxSize));
        }
        
        // Kiểm tra loại file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            return $this->error("File type not allowed: {$mimeType}");
        }
        
        // Kiểm tra extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return $this->error("File extension not allowed: {$extension}");
        }
        
        // Quét virus (nếu có)
        if (!$this->scanVirus($file['tmp_name'])) {
            return $this->error("File contains malware or virus");
        }
        
        // Tạo tên file an toàn
        $safeFilename = $this->generateSafeFilename($file['name']);
        
        // Tạo thư mục đích
        $targetDir = $this->uploadPath . $subDir . '/';
        $this->ensureDirectoryExists($targetDir);
        
        // Tạo thư mục con theo ngày
        $dateDir = date('Y/m/d/');
        $targetDir .= $dateDir;
        $this->ensureDirectoryExists($targetDir);
        
        $targetPath = $targetDir . $safeFilename;
        
        // Di chuyển file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $this->error("Failed to move uploaded file");
        }
        
        // Xử lý ảnh nếu cần
        if (strpos($mimeType, 'image/') === 0 && !empty($options['resize'])) {
            $this->resizeImage($targetPath, $options['resize']['width'], $options['resize']['height']);
        }
        
        // Tạo thumbnail nếu cần
        if (strpos($mimeType, 'image/') === 0 && !empty($options['thumbnail'])) {
            $this->createThumbnail($targetPath, $options['thumbnail']['width'], $options['thumbnail']['height']);
        }
        
        Logger::info("File uploaded", [
            'filename' => $safeFilename,
            'subdir' => $subDir,
            'size' => $file['size']
        ]);
        
        return [
            'success' => true,
            'filename' => $safeFilename,
            'path' => $targetPath,
            'url' => $this->getUrl($subDir, $dateDir . $safeFilename),
            'size' => $file['size'],
            'mime_type' => $mimeType
        ];
    }
    
    /**
     * Upload avatar
     */
    public function uploadAvatar($file, $userId)
    {
        $result = $this->upload($file, 'avatars', [
            'resize' => ['width' => 200, 'height' => 200],
            'thumbnail' => ['width' => 50, 'height' => 50]
        ]);
        
        if ($result['success']) {
            // Xóa avatar cũ nếu có
            $db = \Core\Database::getInstance();
            $oldAvatar = $db->fetchColumn("SELECT avatar FROM users WHERE id = ?", [$userId]);
            if ($oldAvatar) {
                $oldPath = $this->uploadPath . 'avatars/' . $oldAvatar;
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }
            
            // Cập nhật database
            $db->update('users', ['avatar' => basename($result['path'])], 'id = ?', [$userId]);
        }
        
        return $result;
    }
    
    /**
     * Upload bằng chứng đánh giá
     */
    public function uploadEvidence($file, $resultId)
    {
        return $this->upload($file, 'evidence', [
            'allowed_types' => ['image/jpeg', 'image/png', 'application/pdf']
        ]);
    }
    
    /**
     * Upload báo cáo
     */
    public function uploadReport($file, $reportId)
    {
        return $this->upload($file, 'reports', [
            'allowed_types' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        ]);
    }
    
    /**
     * Xóa file
     */
    public function delete($filePath)
    {
        $fullPath = $this->uploadPath . $filePath;
        
        if (file_exists($fullPath)) {
            unlink($fullPath);
            
            // Xóa thumbnail nếu có
            $thumbPath = preg_replace('/\.([^.]+)$/', '_thumb.$1', $fullPath);
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
            
            Logger::info("File deleted", ['path' => $filePath]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Resize ảnh
     */
    private function resizeImage($imagePath, $width, $height)
    {
        $imageInfo = getimagesize($imagePath);
        $mime = $imageInfo['mime'];
        
        // Tạo ảnh từ file
        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($imagePath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($imagePath);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($imagePath);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($imagePath);
                break;
            default:
                return false;
        }
        
        // Tính toán tỷ lệ
        $origWidth = imagesx($image);
        $origHeight = imagesy($image);
        
        $ratio = min($width / $origWidth, $height / $origHeight);
        $newWidth = round($origWidth * $ratio);
        $newHeight = round($origHeight * $ratio);
        
        // Tạo ảnh mới
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        
        // Giữ trong suốt cho PNG
        if ($mime === 'image/png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        
        // Lưu ảnh
        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($resized, $imagePath, 90);
                break;
            case 'image/png':
                imagepng($resized, $imagePath, 9);
                break;
            case 'image/gif':
                imagegif($resized, $imagePath);
                break;
            case 'image/webp':
                imagewebp($resized, $imagePath, 90);
                break;
        }
        
        imagedestroy($image);
        imagedestroy($resized);
        
        return true;
    }
    
    /**
     * Tạo thumbnail
     */
    private function createThumbnail($imagePath, $width, $height)
    {
        $thumbPath = preg_replace('/\.([^.]+)$/', '_thumb.$1', $imagePath);
        copy($imagePath, $thumbPath);
        $this->resizeImage($thumbPath, $width, $height);
    }
    
    /**
     * Quét virus (giả lập - thực tế có thể tích hợp ClamAV)
     */
    private function scanVirus($filePath)
    {
        // Kiểm tra file có chứa mã độc không
        // Có thể tích hợp ClamAV: exec('clamscan ' . escapeshellarg($filePath), $output, $returnCode);
        
        // Tạm thời cho phép tất cả
        return true;
    }
    
    /**
     * Tạo tên file an toàn
     */
    private function generateSafeFilename($originalName)
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName = time() . '_' . bin2hex(random_bytes(8));
        
        if ($extension) {
            $safeName .= '.' . strtolower($extension);
        }
        
        return $safeName;
    }
    
    /**
     * Tạo URL cho file
     */
    private function getUrl($subDir, $filePath)
    {
        return $_ENV['APP_URL'] . "/uploads/{$subDir}/{$filePath}";
    }
    
    /**
     * Đảm bảo thư mục tồn tại
     */
    private function ensureDirectoryExists($path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
    
    /**
     * Lấy thông báo lỗi upload
     */
    private function getUploadErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return "File exceeds upload_max_filesize directive";
            case UPLOAD_ERR_FORM_SIZE:
                return "File exceeds MAX_FILE_SIZE directive";
            case UPLOAD_ERR_PARTIAL:
                return "File was only partially uploaded";
            case UPLOAD_ERR_NO_FILE:
                return "No file was uploaded";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Missing temporary folder";
            case UPLOAD_ERR_CANT_WRITE:
                return "Failed to write file to disk";
            case UPLOAD_ERR_EXTENSION:
                return "File upload stopped by extension";
            default:
                return "Unknown upload error";
        }
    }
    
    /**
     * Format bytes
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Trả về lỗi
     */
    private function error($message)
    {
        return [
            'success' => false,
            'error' => $message
        ];
    }
}