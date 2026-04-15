<?php
/**
 * Role-Based Access Control (RBAC)
 * Quản lý 3 loại tài khoản: ADMIN, SECURITY_OFFICER, VIEWER
 * 
 * @package Core
 */

namespace Core;

class RBAC
{
    // Role constants
    const ROLE_ADMIN = 'admin';
    const ROLE_SECURITY_OFFICER = 'security_officer';
    const ROLE_VIEWER = 'viewer';
    
    // Permission constants - User Management
    const PERM_USER_VIEW = 'user.view';
    const PERM_USER_CREATE = 'user.create';
    const PERM_USER_EDIT = 'user.edit';
    const PERM_USER_DELETE = 'user.delete';
    
    // Permission constants - Criteria Management
    const PERM_CRITERIA_VIEW = 'criteria.view';
    const PERM_CRITERIA_CREATE = 'criteria.create';
    const PERM_CRITERIA_EDIT = 'criteria.edit';
    const PERM_CRITERIA_DELETE = 'criteria.delete';
    const PERM_CRITERIA_IMPORT = 'criteria.import';
    const PERM_CRITERIA_EXPORT = 'criteria.export';
    
    // Permission constants - Server Management
    const PERM_SERVER_VIEW = 'server.view';
    const PERM_SERVER_CREATE = 'server.create';
    const PERM_SERVER_EDIT = 'server.edit';
    const PERM_SERVER_DELETE = 'server.delete';
    
    // Permission constants - Assessment
    const PERM_ASSESSMENT_VIEW = 'assessment.view';
    const PERM_ASSESSMENT_RUN = 'assessment.run';
    const PERM_ASSESSMENT_MANUAL = 'assessment.manual';
    
    // Permission constants - Alert Management
    const PERM_ALERT_VIEW = 'alert.view';
    const PERM_ALERT_ACKNOWLEDGE = 'alert.acknowledge';
    const PERM_ALERT_RESOLVE = 'alert.resolve';
    
    // Permission constants - Report
    const PERM_REPORT_VIEW = 'report.view';
    const PERM_REPORT_EXPORT = 'report.export';
    
    // Permission constants - Backup
    const PERM_BACKUP_VIEW = 'backup.view';
    const PERM_BACKUP_CREATE = 'backup.create';
    const PERM_BACKUP_RESTORE = 'backup.restore';
    
    /**
     * @var Database Database instance
     */
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Lấy role của user
     * 
     * @param int $userId
     * @return string
     */
    public function getUserRole($userId)
    {
        $sql = "SELECT r.name FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE u.id = ?";
        $result = $this->db->fetchOne($sql, [$userId]);
        return $result['name'] ?? self::ROLE_VIEWER;
    }
    
    /**
     * Kiểm tra user có permission không
     * 
     * @param int $userId
     * @param string $permission
     * @return bool
     */
    public function hasPermission($userId, $permission)
    {
        $role = $this->getUserRole($userId);
        $permissions = $this->getRolePermissions($role);
        return in_array($permission, $permissions) || in_array('*', $permissions);
    }
    
    /**
     * Lấy danh sách permissions theo role
     * 
     * @param string $role
     * @return array
     */
    public function getRolePermissions($role)
    {
        $permissions = [
            // ADMIN: có tất cả quyền
            self::ROLE_ADMIN => ['*'],
            
            // SECURITY_OFFICER: quyền về an ninh, không quản lý user
            self::ROLE_SECURITY_OFFICER => [
                self::PERM_CRITERIA_VIEW,
                self::PERM_SERVER_VIEW,
                self::PERM_SERVER_CREATE,
                self::PERM_SERVER_EDIT,
                self::PERM_ASSESSMENT_VIEW,
                self::PERM_ASSESSMENT_RUN,
                self::PERM_ASSESSMENT_MANUAL,
                self::PERM_ALERT_VIEW,
                self::PERM_ALERT_ACKNOWLEDGE,
                self::PERM_ALERT_RESOLVE,
                self::PERM_REPORT_VIEW,
                self::PERM_REPORT_EXPORT,
            ],
            
            // VIEWER: chỉ xem
            self::ROLE_VIEWER => [
                self::PERM_CRITERIA_VIEW,
                self::PERM_SERVER_VIEW,
                self::PERM_ASSESSMENT_VIEW,
                self::PERM_ALERT_VIEW,
                self::PERM_REPORT_VIEW,
            ]
        ];
        
        return $permissions[$role] ?? $permissions[self::ROLE_VIEWER];
    }
    
    /**
     * Lấy menu theo role (cho frontend)
     * 
     * @param string $role
     * @return array
     */
    public function getMenuByRole($role)
    {
        $menus = [
            self::ROLE_ADMIN => [
                ['icon' => '📊', 'name' => 'Dashboard', 'path' => '/dashboard'],
                ['icon' => '🖥️', 'name' => 'Servers', 'path' => '/servers'],
                ['icon' => '📂', 'name' => 'Categories', 'path' => '/categories'],
                ['icon' => '✅', 'name' => 'Criteria (280)', 'path' => '/criteria'],
                ['icon' => '🔍', 'name' => 'Assessments', 'path' => '/assessments'],
                ['icon' => '⚠️', 'name' => 'Vulnerabilities', 'path' => '/vulnerabilities'],
                ['icon' => '🔔', 'name' => 'Alerts', 'path' => '/alerts'],
                ['icon' => '👥', 'name' => 'Users', 'path' => '/users'],
                ['icon' => '⚙️', 'name' => 'Roles', 'path' => '/roles'],
                ['icon' => '💾', 'name' => 'Backup', 'path' => '/backup'],
                ['icon' => '📄', 'name' => 'Reports', 'path' => '/reports'],
                ['icon' => '👤', 'name' => 'Profile', 'path' => '/profile'],
            ],
            
            self::ROLE_SECURITY_OFFICER => [
                ['icon' => '📊', 'name' => 'Dashboard', 'path' => '/dashboard'],
                ['icon' => '🖥️', 'name' => 'Servers', 'path' => '/servers'],
                ['icon' => '📂', 'name' => 'Categories', 'path' => '/categories'],
                ['icon' => '✅', 'name' => 'Criteria (280)', 'path' => '/criteria'],
                ['icon' => '🔍', 'name' => 'Assessments', 'path' => '/assessments'],
                ['icon' => '⚠️', 'name' => 'Vulnerabilities', 'path' => '/vulnerabilities'],
                ['icon' => '🔔', 'name' => 'Alerts', 'path' => '/alerts'],
                ['icon' => '📄', 'name' => 'Reports', 'path' => '/reports'],
                ['icon' => '👤', 'name' => 'Profile', 'path' => '/profile'],
            ],
            
            self::ROLE_VIEWER => [
                ['icon' => '📊', 'name' => 'Dashboard', 'path' => '/dashboard'],
                ['icon' => '📂', 'name' => 'Categories', 'path' => '/categories'],
                ['icon' => '✅', 'name' => 'Criteria (280)', 'path' => '/criteria'],
                ['icon' => '🔍', 'name' => 'Assessments', 'path' => '/assessments'],
                ['icon' => '⚠️', 'name' => 'Vulnerabilities', 'path' => '/vulnerabilities'],
                ['icon' => '🔔', 'name' => 'Alerts', 'path' => '/alerts'],
                ['icon' => '📄', 'name' => 'Reports', 'path' => '/reports'],
                ['icon' => '👤', 'name' => 'Profile', 'path' => '/profile'],
            ]
        ];
        
        return $menus[$role] ?? $menus[self::ROLE_VIEWER];
    }
    
    /**
     * Kiểm tra xem user có thể truy cập URL không
     * 
     * @param int $userId
     * @param string $url
     * @return bool
     */
    public function canAccessUrl($userId, $url)
    {
        $role = $this->getUserRole($userId);
        $allowedUrls = $this->getAllowedUrlsByRole($role);
        
        foreach ($allowedUrls as $pattern) {
            if (fnmatch($pattern, $url)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Danh sách URL được phép theo role
     * 
     * @param string $role
     * @return array
     */
    private function getAllowedUrlsByRole($role)
    {
        $urls = [
            self::ROLE_ADMIN => ['*'], // Admin được phép tất cả
            
            self::ROLE_SECURITY_OFFICER => [
                '/dashboard*', '/servers*', '/categories*', '/criteria*',
                '/assessments*', '/vulnerabilities*', '/alerts*', '/reports*',
                '/profile*'
            ],
            
            self::ROLE_VIEWER => [
                '/dashboard*', '/categories*', '/criteria*',
                '/assessments*', '/vulnerabilities*', '/alerts*', '/reports*',
                '/profile*'
            ]
        ];
        
        return $urls[$role] ?? $urls[self::ROLE_VIEWER];
    }
}