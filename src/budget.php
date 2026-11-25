<?php
declare(strict_types=1);

class BudgetService {
    public function __construct(private PDO $db) {}

    public function getCurrentBudgets(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT b.budget_id, b.category_id, b.amount_limit, b.period, b.created_at,
                   COALESCE(c.name, 'Tất cả khoản chi') AS category_name,
                   COALESCE(c.icon, 'layers') AS icon,
                   COALESCE(c.color, '#3B82F6') AS color
            FROM budgets b
            LEFT JOIN categories c ON b.category_id = c.category_id
            WHERE b.user_id = ?
            ORDER BY b.budget_id DESC
        ");
        $stmt->execute([$userId]);
        $budgets = $stmt->fetchAll();

        $today = date('Y-m-d');
        foreach ($budgets as &$b) {
            $spent = $this->calculateSpentInPeriod(
                $userId,
                $b['category_id'] !== null ? (int)$b['category_id'] : null,
                $b['period'],
                $today
            );
            $limit = (float)$b['amount_limit'];
            $percent = $limit > 0 ? round(($spent / $limit) * 100, 1) : 0.0;

            // Phân loại 3 ngưỡng màu chuẩn SR-5.1
            $statusLevel = 'SAFE';     // < 70%: Xanh
            if ($percent >= 90) {
                $statusLevel = 'DANGER';   // >= 90%: Đỏ
            } elseif ($percent >= 70) {
                $statusLevel = 'WARNING';  // 70% - < 90%: Vàng/Cam
            }

            $b['spent_amount'] = $spent;
            $b['remaining_amount'] = max(0, $limit - $spent);
            $b['percentage'] = $percent;
            $b['status_level'] = $statusLevel;
        }
        return $budgets;
    }

    public function create(int $userId, array $data): array {
        $limit = (float)($data['amount_limit'] ?? 0);
        $period = $data['period'] ?? 'Hàng tháng';
        $categoryId = !empty($data['category_id']) ? (int)$data['category_id'] : null;

        if ($limit <= 0) {
            throw new InvalidArgumentException('Số tiền hạn mức phải lớn hơn 0.');
        }
        if (!in_array($period, ['Hàng tuần', 'Hàng tháng'], true)) {
            throw new InvalidArgumentException('Chu kỳ lặp lại phải là Hàng tuần hoặc Hàng tháng.');
        }
        if ($categoryId !== null) {
            $cat = OwnershipGuard::assertOwner($this->db, 'categories', $categoryId, $userId);
            if ($cat['type'] !== 'Chi') {
                throw new InvalidArgumentException('Ngân sách chỉ áp dụng cho danh mục Chi.');
            }
        }

        // Chặn trùng lặp ngân sách cùng danh mục và chu kỳ (xử lý cả NULL)
        if ($categoryId === null) {
            $dup = $this->db->prepare("SELECT budget_id FROM budgets WHERE user_id = ? AND category_id IS NULL AND period = ?");
            $dup->execute([$userId, $period]);
        } else {
            $dup = $this->db->prepare("SELECT budget_id FROM budgets WHERE user_id = ? AND category_id = ? AND period = ?");
            $dup->execute([$userId, $categoryId, $period]);
        }
        if ($dup->fetch()) {
            throw new RuntimeException('Đã tồn tại ngân sách cho danh mục và chu kỳ này.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO budgets (user_id, category_id, amount_limit, period)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $categoryId, $limit, $period]);

        return $this->getCurrentBudgets($userId);
    }

    public function update(int $userId, int $budgetId, array $data): array {
        $budget = OwnershipGuard::assertOwner($this->db, 'budgets', $budgetId, $userId);

        $limit = isset($data['amount_limit']) ? (float)$data['amount_limit'] : (float)$budget['amount_limit'];
        $period = $data['period'] ?? $budget['period'];

        if ($limit <= 0) {
            throw new InvalidArgumentException('Số tiền hạn mức phải lớn hơn 0.');
        }
        if (!in_array($period, ['Hàng tuần', 'Hàng tháng'], true)) {
            throw new InvalidArgumentException('Chu kỳ lặp lại không hợp lệ.');
        }

        $stmt = $this->db->prepare("
            UPDATE budgets SET amount_limit = ?, period = ?
            WHERE budget_id = ? AND user_id = ?
        ");
        $stmt->execute([$limit, $period, $budgetId, $userId]);

        return $this->getCurrentBudgets($userId);
    }

    public function delete(int $userId, int $budgetId): void {
        OwnershipGuard::assertOwner($this->db, 'budgets', $budgetId, $userId);
        $this->db->prepare("DELETE FROM budgets WHERE budget_id = ? AND user_id = ?")
                 ->execute([$budgetId, $userId]);
    }

    /**
     * SR-5.3: Đối chiếu hạn mức và sinh cảnh báo tự động khi chạm ngưỡng 80% hoặc vượt 100%
     */
    public function evaluateAfterExpense(int $userId, int $categoryId, string $txDate): void {
        $stmt = $this->db->prepare("
            SELECT b.budget_id, b.category_id, b.amount_limit, b.period,
                   COALESCE(c.name, 'Tất cả khoản chi') AS category_name
            FROM budgets b
            LEFT JOIN categories c ON b.category_id = c.category_id
            WHERE b.user_id = ? AND (b.category_id = ? OR b.category_id IS NULL)
        ");
        $stmt->execute([$userId, $categoryId]);

        foreach ($stmt->fetchAll() as $b) {
            $spent = $this->calculateSpentInPeriod(
                $userId,
                $b['category_id'] !== null ? (int)$b['category_id'] : null,
                $b['period'],
                $txDate
            );
            $limit = (float)$b['amount_limit'];
            $ratio = ($spent / $limit) * 100;

            if ($ratio >= 100) {
                $msg = sprintf(
                    "Ngân sách '%s' (%s) đã chi %sđ / %sđ (%.1f%% - Vượt hạn mức).",
                    $b['category_name'],
                    $b['period'],
                    number_format($spent, 0, ',', '.'),
                    number_format($limit, 0, ',', '.'),
                    $ratio
                );
                $this->insertNotification($userId, 'Cảnh báo vượt hạn mức ngân sách', $msg, 'BUDGET_EXCEEDED');
            } elseif ($ratio >= 80) {
                $msg = sprintf(
                    "Ngân sách '%s' (%s) đã sử dụng %.1f%% hạn mức (%sđ / %sđ).",
                    $b['category_name'],
                    $b['period'],
                    $ratio,
                    number_format($spent, 0, ',', '.'),
                    number_format($limit, 0, ',', '.')
                );
                $this->insertNotification($userId, 'Sắp chạm hạn mức ngân sách', $msg, 'BUDGET_WARNING');
            }
        }
    }

    public function calculateSpentInPeriod(int $userId, ?int $categoryId, string $period, string $refDate): float {
        $dateFilter = $period === 'Hàng tuần'
            ? "YEARWEEK(transaction_date, 1) = YEARWEEK(?, 1)"
            : "DATE_FORMAT(transaction_date, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')";

        $sql = "
            SELECT COALESCE(SUM(amount), 0)
            FROM transactions
            WHERE user_id = ? AND type = 'Chi' AND {$dateFilter}
        ";
        $params = [$userId, $refDate];

        if ($categoryId !== null) {
            $sql .= " AND category_id = ?";
            $params[] = $categoryId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    private function insertNotification(int $userId, string $title, string $message, string $type): void {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $title, $message, $type]);
    }
}