<?php
declare(strict_types=1);

class UserProfileService {
    public function __construct(private PDO $db) {}

    /**
     * Khởi tạo vùng dữ liệu ban đầu & nhân bản danh mục mặc định của hệ thống cho user mới
     */
    public function initializeUserWorkspace(int $userId): void {
        // 1. Nhân bản danh mục mặc định (user_id IS NULL) sang danh mục cá nhân
        $sqlCloneCategories = "
            INSERT INTO categories (user_id, name, type, icon, color, is_hidden)
            SELECT ?, name, type, icon, color, 0
            FROM categories
            WHERE user_id IS NULL AND is_hidden = 0
        ";
        $this->db->prepare($sqlCloneCategories)->execute([$userId]);

        // 2. Tạo sẵn 1 ví Tiền mặt mặc định với số dư 0
        $sqlInitWallet = "
            INSERT INTO wallets (user_id, name, icon, initial_balance, current_balance)
            VALUES (?, 'Tiền mặt', 'wallet', 0.00, 0.00)
        ";
        $this->db->prepare($sqlInitWallet)->execute([$userId]);
    }

    public function getProfile(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT user_id, email, full_name, phone, gender, currency, role, status, created_at 
            FROM users WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: [];
    }

 public function updateProfile(int $userId, array $data): array {
    $fullName = trim($data['full_name'] ?? '');
    if ($fullName === '' || !preg_match('/^[\p{L}\s]+$/u', $fullName)) {
        throw new InvalidArgumentException('Họ tên không được để trống và không chứa ký tự đặc biệt.');
    }

    $phone = !empty($data['phone']) ? trim($data['phone']) : null;
    if ($phone !== null && !preg_match('/^0\d{9}$/', $phone)) {
        throw new InvalidArgumentException('Số điện thoại phải gồm đúng 10 chữ số và bắt đầu bằng số 0.');
    }

    $gender = in_array($data['gender'] ?? '', ['Nam', 'Nữ', 'Khác'], true) ? $data['gender'] : null;
    $currency = !empty($data['currency']) ? strtoupper(trim($data['currency'])) : 'VND';

    $stmt = $this->db->prepare("
        UPDATE users SET full_name = ?, phone = ?, gender = ?, currency = ? WHERE user_id = ?
    ");
    $stmt->execute([$fullName, $phone, $gender, $currency, $userId]);

    return $this->getProfile($userId);
}
}