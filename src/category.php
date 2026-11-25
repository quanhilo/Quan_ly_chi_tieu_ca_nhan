<?php
declare(strict_types=1);

class CategoryService {
    public function __construct(private PDO $db) {}

    public function getUserCategories(int $userId, ?string $type = null, string $search = ''): array {
        // Thống kê tổng quan thanh Summary Bar (SR-13)
        $statStmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN type = 'Chi' THEN 1 ELSE 0 END) AS expense_count,
                SUM(CASE WHEN type = 'Thu' THEN 1 ELSE 0 END) AS income_count
            FROM categories
            WHERE user_id = ? AND is_hidden = 0
        ");
        $statStmt->execute([$userId]);
        $stats = $statStmt->fetch();

        $sql = "
            SELECT category_id, name, type, icon, color
            FROM categories
            WHERE user_id = ? AND is_hidden = 0
        ";
        $params = [$userId];

        if ($type && in_array($type, ['Thu', 'Chi'], true)) {
            $sql .= " AND type = ?";
            $params[] = $type;
        }
        if (trim($search) !== '') {
            $sql .= " AND name LIKE ?";
            $params[] = '%' . trim($search) . '%';
        }
        $sql .= " ORDER BY category_id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return [
            'summary'    => [
                'total'         => (int)($stats['total'] ?? 0),
                'expense_count' => (int)($stats['expense_count'] ?? 0),
                'income_count'  => (int)($stats['income_count'] ?? 0),
            ],
            'categories' => $stmt->fetchAll(),
        ];
    }

    public function create(int $userId, array $data): array {
        $name = trim($data['name'] ?? '');
        $type = $data['type'] ?? '';
        $icon = trim($data['icon'] ?? 'tag');
        $color = trim($data['color'] ?? '#10B981');

        if ($name === '') {
            throw new InvalidArgumentException('Tên danh mục không được để trống.');
        }
        if (!in_array($type, ['Thu', 'Chi'], true)) {
            throw new InvalidArgumentException('Phân loại danh mục phải là Thu hoặc Chi.');
        }

        // Kiểm tra trùng tên trong cùng phân loại Thu/Chi
        $dup = $this->db->prepare("
            SELECT category_id FROM categories
            WHERE user_id = ? AND name = ? AND type = ? AND is_hidden = 0
        ");
        $dup->execute([$userId, $name, $type]);
        if ($dup->fetch()) {
            throw new RuntimeException("Danh mục '{$name}' đã tồn tại trong nhóm {$type}.");
        }

        $stmt = $this->db->prepare("
            INSERT INTO categories (user_id, name, type, icon, color, is_hidden)
            VALUES (?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([$userId, $name, $type, $icon, $color]);
        $id = (int)$this->db->lastInsertId();

        return OwnershipGuard::assertOwner($this->db, 'categories', $id, $userId);
    }

    public function update(int $userId, int $categoryId, array $data): array {
        $cat = OwnershipGuard::assertOwner($this->db, 'categories', $categoryId, $userId);

        // Khóa phân loại (Thu/Chi) theo SR-4.3
        if (isset($data['type']) && $data['type'] !== $cat['type']) {
            throw new InvalidArgumentException('Không được phép thay đổi phân loại Thu/Chi của danh mục.');
        }

        $name = trim($data['name'] ?? $cat['name']);
        if ($name === '') {
            throw new InvalidArgumentException('Tên danh mục không được để trống.');
        }
        $icon = trim($data['icon'] ?? $cat['icon']);
        $color = trim($data['color'] ?? $cat['color']);

        $dup = $this->db->prepare("
            SELECT category_id FROM categories
            WHERE user_id = ? AND name = ? AND type = ? AND category_id <> ? AND is_hidden = 0
        ");
        $dup->execute([$userId, $name, $cat['type'], $categoryId]);
        if ($dup->fetch()) {
            throw new RuntimeException("Tên danh mục '{$name}' đã tồn tại.");
        }

        $stmt = $this->db->prepare("
            UPDATE categories SET name = ?, icon = ?, color = ?
            WHERE category_id = ? AND user_id = ?
        ");
        $stmt->execute([$name, $icon, $color, $categoryId, $userId]);

        return OwnershipGuard::assertOwner($this->db, 'categories', $categoryId, $userId);
    }

    /**
     * SR-4.4: Xóa vĩnh viễn nếu trống; nếu có giao dịch thì hỗ trợ MERGE sang danh mục khác hoặc HIDE
     */
    public function deleteOrMerge(int $userId, int $categoryId, string $mode = 'DELETE', ?int $targetCategoryId = null): string {
        $source = OwnershipGuard::assertOwner($this->db, 'categories', $categoryId, $userId);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND category_id = ?");
        $stmt->execute([$userId, $categoryId]);
        $txCount = (int)$stmt->fetchColumn();

        if ($txCount === 0 && $mode === 'DELETE') {
            $this->db->prepare("DELETE FROM categories WHERE category_id = ? AND user_id = ?")
                     ->execute([$categoryId, $userId]);
            return 'Đã xóa danh mục thành công.';
        }

        if ($mode === 'HIDE') {
            $this->db->prepare("UPDATE categories SET is_hidden = 1 WHERE category_id = ? AND user_id = ?")
                     ->execute([$categoryId, $userId]);
            return 'Đã ẩn danh mục khỏi danh sách nhập giao dịch mới.';
        }

        if ($mode === 'MERGE') {
            if (!$targetCategoryId || $targetCategoryId === $categoryId) {
                throw new InvalidArgumentException('Vui lòng chọn danh mục đích hợp lệ để gộp dữ liệu.');
            }
            $target = OwnershipGuard::assertOwner($this->db, 'categories', $targetCategoryId, $userId);
            if ($target['type'] !== $source['type'] || (int)$target['is_hidden'] === 1) {
                throw new InvalidArgumentException('Danh mục đích phải đang hoạt động và cùng phân loại Thu/Chi.');
            }

            $this->db->beginTransaction();
            try {
                // Dồn toàn bộ giao dịch sang danh mục đích
                $this->db->prepare("UPDATE transactions SET category_id = ? WHERE user_id = ? AND category_id = ?")
                         ->execute([$targetCategoryId, $userId, $categoryId]);
                // Xóa danh mục cũ
                $this->db->prepare("DELETE FROM categories WHERE category_id = ? AND user_id = ?")
                         ->execute([$categoryId, $userId]);
                $this->db->commit();
                return "Đã chuyển toàn bộ giao dịch sang '{$target['name']}' và xóa danh mục cũ.";
            } catch (Throwable $e) {
                $this->db->rollBack();
                throw $e;
            }
        }

        throw new RuntimeException('Danh mục này đang có giao dịch. Vui lòng chọn Chuyển tiếp (Gộp) hoặc Ẩn danh mục.');
    }
}