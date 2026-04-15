<?php
/**
 * JSON Web Token (JWT) Handler
 * Tạo và xác thực JWT token cho API authentication
 * 
 * @package Core
 */

namespace Core;

class JWT
{
    /**
     * @var string Secret key for signing
     */
    private static $secret;
    
    /**
     * @var string Signing algorithm
     */
    private static $algorithm = 'HS256';
    
    /**
     * Initialize JWT with secret key
     */
    private static function init()
    {
        if (!self::$secret) {
            self::$secret = $_ENV['JWT_SECRET'] ?? 'default-secret-key-change-me-in-production';
        }
    }
    
    /**
     * Encode payload thành JWT token
     * 
     * @param array $payload Dữ liệu cần encode
     * @return string JWT token
     */
    public static function encode($payload)
    {
        self::init();
        
        // Header
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => self::$algorithm
        ]);
        
        // Payload với thời gian
        $payload['iat'] = time(); // Issued at
        $payload['exp'] = time() + ($_ENV['JWT_TTL'] ?? 3600); // Expiration
        
        // Encode header and payload
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));
        
        // Create signature
        $signature = hash_hmac('sha256', 
            $base64UrlHeader . "." . $base64UrlPayload, 
            self::$secret, 
            true
        );
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        // Return token
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    /**
     * Decode và xác thực JWT token
     * 
     * @param string $token JWT token
     * @return object|null Payload nếu hợp lệ, null nếu không
     */
    public static function decode($token)
    {
        self::init();
        
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        
        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
        
        // Verify signature
        $signature = self::base64UrlDecode($base64UrlSignature);
        $expectedSignature = hash_hmac('sha256', 
            $base64UrlHeader . "." . $base64UrlPayload, 
            self::$secret, 
            true
        );
        
        if (!hash_equals($signature, $expectedSignature)) {
            return null;
        }
        
        // Decode payload
        $payload = json_decode(self::base64UrlDecode($base64UrlPayload));
        
        // Check expiration
        if (isset($payload->exp) && $payload->exp < time()) {
            return null; // Token expired
        }
        
        return $payload;
    }
    
    /**
     * Refresh token (tạo token mới từ token cũ)
     * 
     * @param string $token Old token
     * @return string|null New token
     */
    public static function refresh($token)
    {
        $payload = self::decode($token);
        if (!$payload) {
            return null;
        }
        
        // Remove time claims
        unset($payload->iat);
        unset($payload->exp);
        
        // Create new token
        return self::encode((array)$payload);
    }
    
    /**
     * Get user ID from token
     * 
     * @param string $token JWT token
     * @return int|null
     */
    public static function getUserId($token)
    {
        $payload = self::decode($token);
        return $payload->user_id ?? null;
    }
    
    /**
     * Get user role from token
     * 
     * @param string $token JWT token
     * @return string|null
     */
    public static function getUserRole($token)
    {
        $payload = self::decode($token);
        return $payload->role ?? null;
    }
    
    /**
     * Base64 URL safe encode
     * 
     * @param string $data
     * @return string
     */
    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL safe decode
     * 
     * @param string $data
     * @return string
     */
    private static function base64UrlDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}