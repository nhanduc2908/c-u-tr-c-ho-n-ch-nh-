<?php
/**
 * NOTIFICATION SERVICE
 * 
 * Gửi thông báo qua email, SMS, Webhook
 * - Email (SMTP)
 * - SMS (Twilio, Vietnamobile)
 * - Webhook (Slack, Discord, Teams)
 * 
 * @package Services
 */

namespace Services;

use Core\Database;
use Core\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class NotificationService
{
    private $db;
    private $mailer;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->initMailer();
    }
    
    /**
     * Khởi tạo PHPMailer
     */
    private function initMailer()
    {
        $this->mailer = new PHPMailer(true);
        
        $smtpHost = $_ENV['MAIL_HOST'] ?? '';
        $smtpPort = $_ENV['MAIL_PORT'] ?? 587;
        $smtpUsername = $_ENV['MAIL_USERNAME'] ?? '';
        $smtpPassword = $_ENV['MAIL_PASSWORD'] ?? '';
        $smtpEncryption = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
        
        if ($smtpHost && $smtpUsername && $smtpPassword) {
            $this->mailer->isSMTP();
            $this->mailer->Host = $smtpHost;
            $this->mailer->Port = $smtpPort;
            $this->mailer->Username = $smtpUsername;
            $this->mailer->Password = $smtpPassword;
            $this->mailer->SMTPSecure = $smtpEncryption;
            $this->mailer->SMTPAuth = true;
        }
        
        $this->mailer->setFrom(
            $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@security.com',
            $_ENV['MAIL_FROM_NAME'] ?? 'Security Platform'
        );
        $this->mailer->isHTML(true);
        $this->mailer->CharSet = 'UTF-8';
    }
    
    /**
     * Gửi email
     * 
     * @param string $to Email người nhận
     * @param string $subject Tiêu đề
     * @param string $body Nội dung HTML
     * @param string $altBody Nội dung text
     * @return bool
     */
    public function sendEmail($to, $subject, $body, $altBody = null)
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = $altBody ?? strip_tags($body);
            
            $this->mailer->send();
            
            Logger::info("Email sent", ['to' => $to, 'subject' => $subject]);
            return true;
            
        } catch (Exception $e) {
            Logger::error("Email failed", ['to' => $to, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Gửi email chào mừng
     */
    public function sendWelcomeEmail($to, $username, $password)
    {
        $subject = "Chào mừng đến với Security Assessment Platform";
        
        $body = "
        <html>
        <head><style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8f9fa; }
            .credentials { background: white; padding: 15px; border-radius: 8px; margin: 15px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style></head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🔐 Security Assessment Platform</h2>
                </div>
                <div class='content'>
                    <h3>Xin chào {$username}!</h3>
                    <p>Tài khoản của bạn đã được tạo thành công trên hệ thống Security Assessment Platform.</p>
                    <div class='credentials'>
                        <p><strong>Thông tin đăng nhập:</strong></p>
                        <p>Username: <strong>{$username}</strong></p>
                        <p>Mật khẩu: <strong>{$password}</strong></p>
                        <p><em>Vui lòng đổi mật khẩu sau khi đăng nhập lần đầu.</em></p>
                    </div>
                    <p><a href='" . $_ENV['APP_URL'] . "' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Đăng nhập ngay</a></p>
                </div>
                <div class='footer'>
                    <p>© 2024 Security Assessment Platform. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($to, $subject, $body);
    }
    
    /**
     * Gửi email đặt lại mật khẩu
     */
    public function sendPasswordResetEmail($to, $username, $resetLink)
    {
        $subject = "Đặt lại mật khẩu - Security Assessment Platform";
        
        $body = "
        <html>
        <head><style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc2626; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8f9fa; }
            .button { background: #dc2626; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .warning { background: #fef3c7; padding: 15px; border-radius: 8px; margin: 15px 0; color: #92400e; }
        </style></head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🔐 Đặt lại mật khẩu</h2>
                </div>
                <div class='content'>
                    <h3>Xin chào {$username}!</h3>
                    <p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.</p>
                    <div class='warning'>
                        <p>⚠️ Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.</p>
                    </div>
                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='{$resetLink}' class='button'>Đặt lại mật khẩu</a>
                    </p>
                    <p>Link này sẽ hết hạn sau 1 giờ.</p>
                </div>
                <div class='footer'>
                    <p>© 2024 Security Assessment Platform. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($to, $subject, $body);
    }
    
    /**
     * Gửi email thông báo đăng nhập từ IP lạ
     */
    public function sendNewLoginEmail($to, $username, $ip, $time)
    {
        $subject = "Đăng nhập mới - Security Assessment Platform";
        
        $body = "
        <html>
        <head><style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #eab308; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8f9fa; }
            .info { background: white; padding: 15px; border-radius: 8px; margin: 15px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style></head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🔐 Đăng nhập mới</h2>
                </div>
                <div class='content'>
                    <h3>Xin chào {$username}!</h3>
                    <p>Phát hiện đăng nhập mới vào tài khoản của bạn.</p>
                    <div class='info'>
                        <p><strong>Thông tin đăng nhập:</strong></p>
                        <p>📅 Thời gian: <strong>{$time}</strong></p>
                        <p>🌐 IP: <strong>{$ip}</strong></p>
                        <p>🖥️ Thiết bị: <strong>" . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "</strong></p>
                    </div>
                    <p>Nếu không phải bạn, vui lòng đổi mật khẩu ngay lập tức.</p>
                </div>
                <div class='footer'>
                    <p>© 2024 Security Assessment Platform. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($to, $subject, $body);
    }
    
    /**
     * Gửi email thông báo mật khẩu đã thay đổi
     */
    public function sendPasswordChangedEmail($to, $username)
    {
        $subject = "Mật khẩu đã thay đổi - Security Assessment Platform";
        
        $body = "
        <html>
        <head><style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #22c55e; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8f9fa; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style></head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>✅ Mật khẩu đã thay đổi</h2>
                </div>
                <div class='content'>
                    <h3>Xin chào {$username}!</h3>
                    <p>Mật khẩu tài khoản của bạn vừa được thay đổi thành công.</p>
                    <p>Nếu bạn không thực hiện thay đổi này, vui lòng liên hệ ngay với quản trị viên.</p>
                </div>
                <div class='footer'>
                    <p>© 2024 Security Assessment Platform. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($to, $subject, $body);
    }
    
    /**
     * Gửi email cảnh báo lỗ hổng mới
     */
    public function sendVulnerabilityAlert($to, $vulnerability)
    {
        $subject = "⚠️ Cảnh báo lỗ hổng mới - " . $vulnerability['title'];
        
        $severityColors = [
            'critical' => '#dc2626',
            'high' => '#f97316',
            'medium' => '#eab308',
            'low' => '#22c55e'
        ];
        
        $color = $severityColors[$vulnerability['severity']] ?? '#6b7280';
        
        $body = "
        <html>
        <head><style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: {$color}; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8f9fa; }
            .severity { display: inline-block; padding: 4px 12px; border-radius: 20px; color: white; background: {$color}; font-size: 12px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style></head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>⚠️ Cảnh báo lỗ hổng mới</h2>
                </div>
                <div class='content'>
                    <p><span class='severity'>" . strtoupper($vulnerability['severity']) . "</span></p>
                    <h3>{$vulnerability['title']}</h3>
                    <p>Server: <strong>{$vulnerability['server_name']}</strong></p>
                    <p>{$vulnerability['description']}</p>
                    <p><a href='" . $_ENV['APP_URL'] . "/vulnerabilities/{$vulnerability['id']}' style='background: {$color}; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Xem chi tiết</a></p>
                </div>
                <div class='footer'>
                    <p>© 2024 Security Assessment Platform. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($to, $subject, $body);
    }
    
    /**
     * Gửi email test
     */
    public function sendTestEmail($to, $smtpConfig = null)
    {
        if ($smtpConfig) {
            // Tạm thời thay đổi cấu hình
            $tempMailer = new PHPMailer(true);
            $tempMailer->isSMTP();
            $tempMailer->Host = $smtpConfig['smtp_host'];
            $tempMailer->Port = $smtpConfig['smtp_port'];
            $tempMailer->Username = $smtpConfig['smtp_username'];
            $tempMailer->Password = $smtpConfig['smtp_password'];
            $tempMailer->SMTPSecure = $smtpConfig['smtp_encryption'];
            $tempMailer->SMTPAuth = true;
            $tempMailer->setFrom($smtpConfig['from_address'], $smtpConfig['from_name']);
            $tempMailer->isHTML(true);
            $tempMailer->CharSet = 'UTF-8';
            
            $mailer = $tempMailer;
        } else {
            $mailer = $this->mailer;
        }
        
        try {
            $mailer->clearAddresses();
            $mailer->addAddress($to);
            $mailer->Subject = "Test Email - Security Assessment Platform";
            $mailer->Body = "
            <html>
            <body>
                <h2>✅ Email configuration test</h2>
                <p>If you receive this email, your SMTP configuration is working correctly.</p>
                <p>Time: " . date('Y-m-d H:i:s') . "</p>
            </body>
            </html>
            ";
            
            $mailer->send();
            return true;
            
        } catch (Exception $e) {
            Logger::error("Test email failed", ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Gửi webhook (Slack, Discord, Teams)
     */
    public function sendWebhook($url, $data)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode >= 200 && $httpCode < 300;
    }
    
    /**
     * Gửi thông báo đến Slack
     */
    public function sendToSlack($webhookUrl, $message, $color = '#36a64f')
    {
        $data = [
            'attachments' => [
                [
                    'color' => $color,
                    'text' => $message,
                    'ts' => time()
                ]
            ]
        ];
        
        return $this->sendWebhook($webhookUrl, $data);
    }
    
    /**
     * Gửi thông báo đến Discord
     */
    public function sendToDiscord($webhookUrl, $message, $title = null)
    {
        $data = [
            'content' => $message,
            'username' => 'Security Alert'
        ];
        
        if ($title) {
            $data['embeds'] = [
                [
                    'title' => $title,
                    'description' => $message,
                    'timestamp' => date('c')
                ]
            ];
        }
        
        return $this->sendWebhook($webhookUrl, $data);
    }
    
    /**
     * Gửi email báo cáo
     */
    public function sendReportEmail($recipients, $reportName, $filePath)
    {
        try {
            $this->mailer->clearAddresses();
            foreach ($recipients as $recipient) {
                $this->mailer->addAddress($recipient);
            }
            
            $this->mailer->Subject = "Báo cáo đánh giá bảo mật - {$reportName}";
            $this->mailer->Body = "
            <html>
            <body>
                <h2>📊 Báo cáo đánh giá bảo mật</h2>
                <p>File báo cáo đính kèm.</p>
                <p>Thời gian tạo: " . date('Y-m-d H:i:s') . "</p>
            </body>
            </html>
            ";
            
            // Đính kèm file
            if (file_exists($filePath)) {
                $this->mailer->addAttachment($filePath);
            }
            
            $this->mailer->send();
            return true;
            
        } catch (Exception $e) {
            Logger::error("Report email failed", ['error' => $e->getMessage()]);
            return false;
        }
    }
}