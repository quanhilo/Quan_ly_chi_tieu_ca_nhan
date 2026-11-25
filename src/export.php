<?php
declare(strict_types=1);

class ExportService {
    public function __construct(private PDO $db) {}

    public function downloadTransactionsCsv(int $userId, string $dateFrom, string $dateTo): never {
        $stmt = $this->db->prepare("
            SELECT t.transaction_date, t.type,
                   COALESCE(c.name, 'Chuyển khoản nội bộ') AS category_name,
                   t.amount, COALESCE(t.note, '') AS note,
                   CASE
                       WHEN t.type = 'Thu' THEN tw.name
                       WHEN t.type = 'Chi' THEN sw.name
                       ELSE CONCAT(sw.name, ' -> ', tw.name)
                   END AS wallet_used
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.category_id
            LEFT JOIN wallets sw ON t.source_wallet_id = sw.wallet_id
            LEFT JOIN wallets tw ON t.target_wallet_id = tw.wallet_id
            WHERE t.user_id = ? AND DATE(t.transaction_date) BETWEEN ? AND ?
            ORDER BY t.transaction_date DESC
        ");
        $stmt->execute([$userId, $dateFrom, $dateTo]);
        $rows = $stmt->fetchAll();

        $filename = "Bao_cao_chi_tieu_{$dateFrom}_{$dateTo}.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        // Ghi UTF-8 BOM để Microsoft Excel hiển thị chuẩn tiếng Việt có dấu
        fwrite($out, "\xEF\xBB\xBF");

        // 6 cột đúng đặc tả SR-5.6
        fputcsv($out, ['Ngày tháng', 'Loại giao dịch', 'Danh mục', 'Số tiền', 'Ghi chú', 'Ví sử dụng']);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['transaction_date'],
                $r['type'],
                $r['category_name'],
                $r['amount'],
                $r['note'],
                $r['wallet_used'],
            ]);
        }
        fclose($out);
        exit;
    }
}