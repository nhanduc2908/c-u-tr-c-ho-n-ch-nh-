<?php
/**
 * SETTING MODEL
 * 
 * Quản lý cài đặt hệ thống
 * 
 * @package Models
 */

namespace Models;

use Core\Model;
use Core\Database;
use Core\Cache as Cache;

class Setting extends Model
{
    protected $table = 'system_settings';
    protected $primaryKey = 'id';
    protected $fillable = ['setting_group', 'setting_key', 'setting_value'];
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $timestamps = true;
    
    /**
     * Lấy giá trị setting
     */
    public static function get($group, $key, $default = null)
    {
        $cache = Cache::getInstance();
        $cacheKey = "setting_{$group}_{$key}";
        
        $value = $cache->get($cacheKey);
        if ($value !== null) {
            return $value;
        }
        
        $db = Database::getInstance();
        $result = $db->fetchOne(
            "SELECT setting_value FROM system_settings 
             WHERE setting_group = ? AND setting_key = ?",
            [$group, $key]
        );
        
        $value = $result ? $result['setting_value'] : $default;
        $cache->set($cacheKey, $value, 3600);
        
        return $value;
    }
    
    /**
     * Lấy tất cả setting của một group
     */
    public static function getGroup($group)
    {
        $db = Database::getInstance();
        $settings = $db->fetchAll(
            "SELECT setting_key, setting_value FROM system_settings WHERE setting_group = ?",
            [$group]
        );
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['setting_key']] = $setting['setting_value'];
        }
        
        return $result;
    }
    
    /**
     * Cập nhật setting
     */
    public static function set($group, $key, $value)
    {
        $db = Database::getInstance();
        $cache = Cache::getInstance();
        
        $exists = $db->fetchColumn(
            "SELECT COUNT(*) FROM system_settings WHERE setting_group = ? AND setting_key = ?",
            [$group, $key]
        );
        
        if ($exists) {
            $db->update('system_settings', 
                ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')],
                'setting_group = ? AND setting_key = ?',
                [$group, $key]
            );
        } else {
            $db->insert('system_settings', [
                'setting_group' => $group,
                'setting_key' => $key,
                'setting_value' => $value,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Xóa cache
        $cache->delete("setting_{$group}_{$key}");
        $cache->delete("setting_group_{$group}");
        
        return true;
    }
    
    /**
     * Xóa setting
     */
    public static function delete($group, $key)
    {
        $db = Database::getInstance();
        $cache = Cache::getInstance();
        
        $db->delete('system_settings', 'setting_group = ? AND setting_key = ?', [$group, $key]);
        
        $cache->delete("setting_{$group}_{$key}");
        $cache->delete("setting_group_{$group}");
    }
}