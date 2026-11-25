<?php
declare(strict_types=1);

class DashboardReportService {
    public function __construct(
        private PDO $db,
        private BudgetService $budgetService
    ) {}

    /**
     * SR-11: Dữ liệu Bảng điều khiển (Tổng số dư, Thu/Chi tháng + % biến động, Biểu đồ tuần, Giao dịch gần đây, Cảnh báo)
     */
    public function getDashboard(int $userId, string $monthYm): array {
        if (!preg_match('/^\d{4}-\d{2}$/', $monthYm)) {
            $monthYm = date('Y-m');
        }
        $prevMonthYm = date('Y-m', strtotime($monthYm . '-01 -1 month'));

        // 1. Tổng số dư tài sản hiện có
        $balStmt = $this->db->prepare("SELECT COALESCE(SUM(current_balance), 0) FROM wallets WHERE user_id = ? AND is_hidden = 0");
        $balStmt->execute([$userId]);
        $totalBalance = (float)$balStmt->fetchColumn();

        // 2. Tổng Thu / Chi tháng hiện tại & tháng liền trước
        $curTotals = $this->getMonthIncomeExpense($userId, $monthYm);
        $prevTotals = $this->getMonthIncomeExpense($userId, $prevMonthYm);

        $incomeChangePct = $this->calcPercentChange($prevTotals['income'], $curTotals['income']);
        $expenseChangePct = $this->calcPercentChange($prevTotals['expense'], $curTotals['expense']);

        // 3. Biểu đồ cột đôi Thu - Chi 7 ngày gần nhất
        $weekStmt = $this->db->prepare("
            SELECT DATE(transaction_date) AS day,
                   SUM(CASE WHEN type = 'Thu' THEN amount ELSE 0 END) AS income,
                   SUM(CASE WHEN type = 'Chi' THEN amount ELSE 0 END) AS expense
            FROM transactions
            WHERE user_id = ? AND DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(transaction_date)
            ORDER BY day ASC
        ");
        $weekStmt->execute([$userId]);

        // 4. Giao dịch gần đây (Top 5)
        $recentStmt = $this->db->prepare("
            SELECT t.transaction_id, t.type, t.amount, t.transaction_date,
                   COALESCE(c.name, 'Chuyển khoản nội bộ') AS category_name,
                   COALESCE(c.icon, 'repeat') AS icon,
                   COALESCE(sw.name, tw.name) AS wallet_name
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.category_id
            LEFT JOIN wallets sw ON t.source_wallet_id = sw.wallet_id
            LEFT JOIN wallets tw ON t.target_wallet_id = tw.wallet_id
            WHERE t.user_id = ?
            ORDER BY t.transaction_date DESC, t.transaction_id DESC
            LIMIT 5
        ");
        $recentStmt->execute([$userId]);

        // 5. Thanh cảnh báo ngân sách (>= 80%)
        $alerts = array_values(array_filter(
            $this->budgetService->getCurrentBudgets($userId),
            fn(array $b) => $b['percentage'] >= 80
        ));

        return [
            'month'               => $monthYm,
            'total_balance'       => $totalBalance,
            'monthly_income'      => $curTotals['income'],
            'income_change_pct'   => $incomeChangePct,
            'monthly_expense'     => $curTotals['expense'],
            'expense_change_pct'  => $expenseChangePct,
            'weekly_chart'        => $weekStmt->fetchAll(),
            'recent_transactions' => $recentStmt->fetchAll(),
            'budget_alerts'       => $alerts,
        ];
    }

    /**
     * SR-5.4 & SR-10: Báo cáo tổng quan Thu - Chi & Số dư dự phòng
     */
    public function getCashflowReport(int $userId, string $dateFrom, string $dateTo): array {
        if ($dateFrom > $dateTo) {
            throw new InvalidArgumentException('Ngày bắt đầu không được lớn hơn ngày kết thúc.');
        }

        $sumStmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN type = 'Thu' THEN amount ELSE 0 END), 0) AS total_income,
                COALESCE(SUM(CASE WHEN type = 'Chi' THEN amount ELSE 0 END), 0) AS total_expense
            FROM transactions
            WHERE user_id = ? AND DATE(transaction_date) BETWEEN ? AND ?
        ");
        $sumStmt->execute([$userId, $dateFrom, $dateTo]);
        $totals = $sumStmt->fetch();

        $totalIncome = (float)$totals['total_income'];
        $totalExpense = (float)$totals['total_expense'];
        $netBalance = $totalIncome - $totalExpense;

        $evaluation = $netBalance > 0 ? 'Tích lũy tốt' : ($netBalance === 0.0 ? 'Cân bằng' : 'Thâm hụt ngân sách');

        // Nhóm theo tháng nếu khoảng thời gian > 45 ngày, ngược lại nhóm theo ngày
        $daysDiff = (strtotime($dateTo) - strtotime($dateFrom)) / 86400;
        $groupExpr = $daysDiff > 45 ? "DATE_FORMAT(transaction_date, '%Y-%m')" : "DATE(transaction_date)";

        $chartStmt = $this->db->prepare("
            SELECT {$groupExpr} AS label,
                   COALESCE(SUM(CASE WHEN type = 'Thu' THEN amount ELSE 0 END), 0) AS income,
                   COALESCE(SUM(CASE WHEN type = 'Chi' THEN amount ELSE 0 END), 0) AS expense
            FROM transactions
            WHERE user_id = ? AND DATE(transaction_date) BETWEEN ? AND ?
            GROUP BY label
            ORDER BY label ASC
        ");
        $chartStmt->execute([$userId, $dateFrom, $dateTo]);

        return [
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'total_income'  => $totalIncome,
            'total_expense' => $totalExpense,
            'net_balance'   => $netBalance,
            'evaluation'    => $evaluation,
            'series'        => $chartStmt->fetchAll(),
        ];
    }

    /**
     * SR-5.5 & SR-15: Phân tích cơ cấu chi tiêu (Donut Chart & Danh sách xếp hạng giảm dần)
     */
    public function getExpenseBreakdown(int $userId, string $dateFrom, string $dateTo, string $type = 'Chi'): array {
        if ($dateFrom > $dateTo) {
            throw new InvalidArgumentException('Ngày bắt đầu không được lớn hơn ngày kết thúc.');
        }
        $txType = in_array($type, ['Thu', 'Chi'], true) ? $type : 'Chi';

        $stmt = $this->db->prepare("
            SELECT c.category_id, c.name, c.icon, c.color, COALESCE(SUM(t.amount), 0) AS total_amount
            FROM transactions t
            INNER JOIN categories c ON t.category_id = c.category_id
            WHERE t.user_id = ? AND t.type = ? AND DATE(t.transaction_date) BETWEEN ? AND ?
            GROUP BY c.category_id, c.name, c.icon, c.color
            ORDER BY total_amount DESC
        ");
        $stmt->execute([$userId, $txType, $dateFrom, $dateTo]);
        $items = $stmt->fetchAll();

        $grandTotal = array_reduce($items, fn(float $s, array $i) => $s + (float)$i['total_amount'], 0.0);

        $rank = 1;
        foreach ($items as &$item) {
            $amt = (float)$item['total_amount'];
            $item['rank'] = $rank++;
            $item['total_amount'] = $amt;
            $item['percentage'] = $grandTotal > 0 ? round(($amt / $grandTotal) * 100, 1) : 0.0;
        }

        return [
            'type'        => $txType,
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
            'grand_total' => $grandTotal,
            'breakdown'   => $items,
        ];
    }

    private function getMonthIncomeExpense(int $userId, string $ym): array {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN type = 'Thu' THEN amount ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN type = 'Chi' THEN amount ELSE 0 END), 0) AS expense
            FROM transactions
            WHERE user_id = ? AND DATE_FORMAT(transaction_date, '%Y-%m') = ?
        ");
        $stmt->execute([$userId, $ym]);
        $row = $stmt->fetch();
        return ['income' => (float)$row['income'], 'expense' => (float)$row['expense']];
    }

    private function calcPercentChange(float $prev, float $curr): float {
        if ($prev === 0.0) {
            return $curr > 0 ? 100.0 : 0.0;
        }
        return round((($curr - $prev) / $prev) * 100, 1);
    }
}