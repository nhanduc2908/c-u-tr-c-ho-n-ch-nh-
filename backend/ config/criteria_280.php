<?php
/**
 * 280 TIÊU CHÍ ĐÁNH GIÁ BẢO MẬT (MẪU)
 * 
 * Danh sách mẫu các tiêu chí đánh giá
 * Trong thực tế, dữ liệu này được lưu trong database
 * 
 * @package Config
 */

return [
    // IAM - Identity and Access Management (20 criteria)
    [
        'code' => 'IAM-001',
        'category_code' => 'IAM',
        'name' => 'Không sử dụng tài khoản mặc định',
        'description' => 'Đã vô hiệu hóa hoặc đổi mật khẩu các tài khoản mặc định (root, admin, guest)',
        'severity' => 'critical',
        'weight' => 10,
        'check_method' => 'auto',
        'remediation_guide' => 'Vô hiệu hóa hoặc đổi mật khẩu tất cả tài khoản mặc định',
        'reference_standard' => 'ISO 27001, NIST',
    ],
    [
        'code' => 'IAM-002',
        'category_code' => 'IAM',
        'name' => 'Chính sách mật khẩu mạnh',
        'description' => 'Độ dài tối thiểu 8 ký tự, bao gồm chữ hoa, chữ thường, số, ký tự đặc biệt',
        'severity' => 'high',
        'weight' => 8,
        'check_method' => 'auto',
        'remediation_guide' => 'Cấu hình chính sách mật khẩu mạnh trong hệ thống',
        'reference_standard' => 'ISO 27001, NIST',
    ],
    [
        'code' => 'IAM-003',
        'category_code' => 'IAM',
        'name' => 'Xác thực đa yếu tố (MFA)',
        'description' => 'Kích hoạt MFA cho tài khoản quản trị và tài khoản quan trọng',
        'severity' => 'critical',
        'weight' => 10,
        'check_method' => 'manual',
        'remediation_guide' => 'Cài đặt và cấu hình MFA cho các tài khoản quan trọng',
        'reference_standard' => 'ISO 27001, NIST, PCI-DSS',
    ],
    [
        'code' => 'IAM-004',
        'category_code' => 'IAM',
        'name' => 'Phân quyền tối thiểu',
        'description' => 'Người dùng chỉ có quyền tối thiểu cần thiết cho công việc',
        'severity' => 'high',
        'weight' => 8,
        'check_method' => 'manual',
        'remediation_guide' => 'Rà soát và thu hẹp quyền truy cập của người dùng',
        'reference_standard' => 'ISO 27001, NIST',
    ],
    
    // NET - Network Security (25 criteria)
    [
        'code' => 'NET-001',
        'category_code' => 'NET',
        'name' => 'Firewall được kích hoạt',
        'description' => 'Firewall được bật và cấu hình đúng cách',
        'severity' => 'critical',
        'weight' => 10,
        'check_method' => 'auto',
        'remediation_guide' => 'Kích hoạt và cấu hình firewall phù hợp',
        'reference_standard' => 'ISO 27001, NIST, PCI-DSS',
    ],
    [
        'code' => 'NET-002',
        'category_code' => 'NET',
        'name' => 'Đóng các cổng không cần thiết',
        'description' => 'Các cổng dịch vụ không cần thiết bị đóng',
        'severity' => 'high',
        'weight' => 7,
        'check_method' => 'auto',
        'remediation_guide' => 'Đóng các cổng không sử dụng, chỉ mở cổng cần thiết',
        'reference_standard' => 'ISO 27001, NIST',
    ],
    [
        'code' => 'NET-003',
        'category_code' => 'NET',
        'name' => 'Phân đoạn mạng',
        'description' => 'Mạng được phân đoạn giữa các khu vực khác nhau',
        'severity' => 'high',
        'weight' => 8,
        'check_method' => 'manual',
        'remediation_guide' => 'Thiết lập VLAN và phân đoạn mạng phù hợp',
        'reference_standard' => 'ISO 27001, NIST',
    ],
    
    // SEC - Security Configuration (30 criteria)
    [
        'code' => 'SEC-001',
        'category_code' => 'SEC',
        'name' => 'SSH root login bị vô hiệu hóa',
        'description' => 'Đã tắt đăng nhập root qua SSH',
        'severity' => 'high',
        'weight' => 8,
        'check_method' => 'auto',
        'remediation_guide' => 'Set PermitRootLogin no trong /etc/ssh/sshd_config',
        'reference_standard' => 'ISO 27001, NIST',
    ],
    [
        'code' => 'SEC-002',
        'category_code' => 'SEC',
        'name' => 'Fail2ban được cài đặt',
        'description' => 'Cài đặt và cấu hình Fail2ban để chống brute force',
        'severity' => 'medium',
        'weight' => 5,
        'check_method' => 'auto',
        'remediation_guide' => 'Cài đặt fail2ban và cấu hình bảo vệ SSH',
        'reference_standard' => 'NIST',
    ],
    [
        'code' => 'SEC-003',
        'category_code' => 'SEC',
        'name' => 'Cập nhật bảo mật định kỳ',
        'description' => 'Hệ thống được cập nhật bản vá bảo mật thường xuyên',
        'severity' => 'critical',
        'weight' => 10,
        'check_method' => 'auto',
        'remediation_guide' => 'Thiết lập cập nhật tự động hoặc lịch trình cập nhật',
        'reference_standard' => 'ISO 27001, NIST, PCI-DSS',
    ],
    
    // Thêm các tiêu chí khác...
    // Tổng cộng 280 tiêu chí sẽ được lưu trong database
];