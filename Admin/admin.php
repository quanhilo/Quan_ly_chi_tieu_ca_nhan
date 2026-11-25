<?php
declare(strict_types=1);

class AdminService {
    public function __construct(private PDO $db) {}

    /**
     * SR-19: Bảng điều khiển Quản trị (Tuyệt đối không truy vấn dữ liệu thu/chi cá nhân)
     */
    public function getAdminDashboard(): array {
        $stats = $this->db->query("
            SELECT
                COUNT(*) AS total_users,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS new_this_week,
                SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_users,
                SUM(CASE WHEN status = 'Locked' THEN 1 ELSE 0 END) AS locked_users
            FROM users
            WHERE role = 'User'
        ")->fetch();

        $defaultCatCount = (int)$this->db->query("
            SELECT COUNT(*) FROM categories WHERE user_id IS NULL AND is_hidden = 0
        ")->fetchColumn();

        $totalUsers = (int)($stats['total_users'] ?? 0);
        $activeUsers = (int)($stats['active_users'] ?? 0);
        $activePct = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0.0;

        // Biểu đồ tăng trưởng người dùng mới theo tháng
        $growth = $this->db->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS new_users
            FROM users
            WHERE role = 'User'
            GROUP BY month
            ORDER BY month ASC
            LIMIT 12
        ")->fetchAll();

        // Nhật ký hoạt động gần đây (che email người dùng)
        $logs = $this->db->query("
            SELECT a.log_id, a.action, a.target_entity, a.details, a.created_at, u.email
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.user_id
            ORDER BY a.created_at DESC
            LIMIT 15
        ")->fetchAll();

        foreach ($logs as &$log) {
            $log['masked_email'] = !empty($log['email']) ? self::maskEmail($log['email']) : 'System';
            unset($log['email']);
        }

        return [
            'platform_stats' => [
                'total_users'           => $totalUsers,
                'new_users_this_week'   => (int)($stats['new_this_week'] ?? 0),
                'active_users'          => $activeUsers,
                'active_rate_pct'       => $activePct,
                'locked_users'          => (int)($stats['locked_users'] ?? 0),
                'default_categories'    => $defaultCatCount,
            ],
            'user_growth_chart' => $growth,
            'recent_audit_logs' => $logs,
        ];
    }

    public function getUsersList(string $search = '', ?string $status = null): array {
        $sql = "SELECT user_id, email, full_name, phone, role, status, created_at FROM users WHERE role = 'User'";
        $params = [];

        if (trim($search) !== '') {
            $sql .= " AND (email LIKE ? OR full_name LIKE ?)";
            $kw = '%' . trim($search) . '%';
            $params[] = $kw;
            $params[] = $kw;
        }
        if ($status && in_array($status, ['Pending_Verification', 'Active', 'Locked'], true)) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function toggleUserLock(int $adminId, int $targetUserId, bool $lock): void {
        $newStatus = $lock ? 'Locked' : 'Active';
        // Tăng token_version nếu khóa để đá phiên đăng nhập ngay lập tức
        $stmt = $this->db->prepare("
            UPDATE users
            SET status = ?, token_version = token_version + 1
            WHERE user_id = ? AND role = 'User'
        ");
        $stmt->execute([$newStatus, $targetUserId]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Không tìm thấy tài khoản User hợp lệ.');
        }

        $action = $lock ? 'LOCK_USER' : 'UNLOCK_USER';
        $this->writeLog($adminId, $action, "User#{$targetUserId}", "Admin đổi trạng thái tài khoản sang {$newStatus}");
    }

    /**
     * SR-4.5: Quản lý Bộ danh mục mặc định (chỉ áp dụng cho user đăng ký mới)
     */
    public function getMasterCategories(): array {
        return $this->db->query("
            SELECT category_id, name, type, icon, color, is_hidden, created_at
            FROM categories
            WHERE user_id IS NULL
            ORDER BY type ASC, category_id ASC
        ")->fetchAll();
    }

    public function createMasterCategory(int $adminId, array $data): void {
        $name = trim($data['name'] ?? '');
        $type = $data['type'] ?? '';
        $icon = trim($data['icon'] ?? 'tag');
        $color = trim($data['color'] ?? '#10B981');

        if ($name === '' || !in_array($type, ['Thu', 'Chi'], true)) {
            throw new InvalidArgumentException('Tên danh mục và phân loại (Thu/Chi) không hợp lệ.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO categories (user_id, name, type, icon, color, is_hidden)
            VALUES (NULL, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([$name, $type, $icon, $color]);

        $this->writeLog($adminId, 'CREATE_MASTER_CATEGORY', $name, "Thêm danh mục mặc định ({$type})");
    }

    public function updateMasterCategory(int $adminId, int $categoryId, array $data): void {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE category_id = ? AND user_id IS NULL");
        $stmt->execute([$categoryId]);
        $cat = $stmt->fetch();
        if (!$cat) {
            throw new RuntimeException('Danh mục mặc định không tồn tại.');
        }

        $name = trim($data['name'] ?? $cat['name']);
        $icon = trim($data['icon'] ?? $cat['icon']);
        $color = trim($data['color'] ?? $cat['color']);
        $isHidden = isset($data['is_hidden']) ? (int)(bool)$data['is_hidden'] : (int)$cat['is_hidden'];

        $upd = $this->db->prepare("
            UPDATE categories SET name = ?, icon = ?, color = ?, is_hidden = ?
            WHERE category_id = ? AND user_id IS NULL
        ");
        $upd->execute([$name, $icon, $color, $isHidden, $categoryId]);

        $this->writeLog($adminId, 'UPDATE_MASTER_CATEGORY', "Category#{$categoryId}", "Cập nhật danh mục mặc định '{$name}'");
    }

    public function writeLog(?int $userId, string $action, ?string $target, ?string $details): void {
        $stmt = $this->db->prepare("
            INSERT INTO audit_logs (user_id, action, target_entity, details)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $action, $target, $details]);
    }

    public static function maskEmail(string $email): string {
        if (!str_contains($email, '@')) return '***';
        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, min(3, mb_strlen($local)));
        return $visible . '***@' . $domain;
    }
}