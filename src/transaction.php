<?php
declare(strict_types=1);

class TransactionService {
    public function __construct(
        private PDO $db,
        private BudgetService $budgetService
    ) {}

    public function create(int $userId, array $data, ?array $receiptFile = null): array {
        $type = $data['type'] ?? '';
        $amount = (float)($data['amount'] ?? 0);
        $txDate = $data['transaction_date'] ?? date('Y-m-d H:i:s');
        $note = isset($data['note']) ? trim($data['note']) : null;

        if (!in_array($type, ['Thu', 'Chi', 'Chuyển khoản'], true)) {
            throw new InvalidArgumentException('Loại giao dịch không hợp lệ.');
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('Số tiền giao dịch phải lớn hơn 0.');
        }

        $imageUrl = StorageService::uploadReceipt($receiptFile);

        $this->db->beginTransaction();
        try {
            $categoryId = null;
            $sourceWalletId = null;
            $targetWalletId = null;

            if ($type === 'Thu') {
                $categoryId = (int)($data['category_id'] ?? 0);
                $targetWalletId = (int)($data['target_wallet_id'] ?? 0);
                $cat = OwnershipGuard::assertOwner($this->db, 'categories', $categoryId, $userId);
                if ($cat['type'] !== 'Thu') {
                    throw new InvalidArgumentException('Vui lòng chọn danh mục Thu.');
                }
                OwnershipGuard::assertOwner($this->db, 'wallets', $targetWalletId, $userId);

                $this->adjustWalletBalance($targetWalletId, +$amount, false);
            } elseif ($type === 'Chi') {
                $categoryId = (int)($data['category_id'] ?? 0);
                $sourceWalletId = (int)($data['source_wallet_id'] ?? 0);
                $cat = OwnershipGuard::assertOwner($this->db, 'categories', $categoryId, $userId);
                if ($cat['type'] !== 'Chi') {
                    throw new InvalidArgumentException('Vui lòng chọn danh mục Chi.');
                }
                OwnershipGuard::assertOwner($this->db, 'wallets', $sourceWalletId, $userId);

                // Trừ ví nguồn & kiểm tra số dư khả dụng (SR-10)
                $this->adjustWalletBalance($sourceWalletId, -$amount, true);
            } else {
                // Chuyển khoản nội bộ (SR-3.3 & SR-3.5)
                $sourceWalletId = (int)($data['source_wallet_id'] ?? 0);
                $targetWalletId = (int)($data['target_wallet_id'] ?? 0);
                if ($sourceWalletId === $targetWalletId || !$sourceWalletId || !$targetWalletId) {
                    throw new InvalidArgumentException('Ví nguồn và ví đích phải hợp lệ và không được trùng nhau.');
                }
                OwnershipGuard::assertOwner($this->db, 'wallets', $sourceWalletId, $userId);
                OwnershipGuard::assertOwner($this->db, 'wallets', $targetWalletId, $userId);

                $this->adjustWalletBalance($sourceWalletId, -$amount, true);
                $this->adjustWalletBalance($targetWalletId, +$amount, false);
            }

            $stmt = $this->db->prepare("
                INSERT INTO transactions
                (user_id, type, amount, transaction_date, category_id, source_wallet_id, target_wallet_id, note, image_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId, $type, $amount, $txDate,
                $categoryId, $sourceWalletId, $targetWalletId,
                $note, $imageUrl
            ]);
            $txId = (int)$this->db->lastInsertId();

            if ($type === 'Chi') {
                $this->budgetService->evaluateAfterExpense($userId, $categoryId, $txDate);
            }

            $this->db->commit();
            return $this->getDetail($userId, $txId);
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * SR-3.4: Chỉnh sửa giao dịch -> Rollback số dư ví cũ và tính lại số dư mới
     */
    public function update(int $userId, int $txId, array $data, ?array $receiptFile = null): array {
        $oldTx = OwnershipGuard::assertOwner($this->db, 'transactions', $txId, $userId);

        $type = $data['type'] ?? $oldTx['type'];
        $amount = isset($data['amount']) ? (float)$data['amount'] : (float)$oldTx['amount'];
        $txDate = $data['transaction_date'] ?? $oldTx['transaction_date'];
        $note = array_key_exists('note', $data) ? trim((string)$data['note']) : $oldTx['note'];

        if (!in_array($type, ['Thu', 'Chi', 'Chuyển khoản'], true)) {
            throw new InvalidArgumentException('Loại giao dịch không hợp lệ.');
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('Số tiền giao dịch phải lớn hơn 0.');
        }

        $newImageUrl = StorageService::uploadReceipt($receiptFile) ?? $oldTx['image_url'];

        $this->db->beginTransaction();
        try {
            // 1. Hoàn tác (rollback) số dư của giao dịch cũ
            $this->revertTransactionEffect($oldTx);

            // 2. Áp dụng số dư theo dữ liệu mới
            $categoryId = null;
            $sourceWalletId = null;
            $targetWalletId = null;

            if ($type === 'Thu') {
                $categoryId = (int)($data['category_id'] ?? $oldTx['category_id']);
                $targetWalletId = (int)($data['target_wallet_id'] ?? $oldTx['target_wallet_id']);
                $cat = OwnershipGuard::assertOwner($this->db, 'categories', $categoryId, $userId);
                if ($cat['type'] !== 'Thu') {
                    throw new InvalidArgumentException('Danh mục không khớp loại Thu.');
                }
                OwnershipGuard::assertOwner($this->db, 'wallets', $targetWalletId, $userId);
                $this->adjustWalletBalance($targetWalletId, +$amount, false);
            } elseif ($type === 'Chi') {
                $categoryId = (int)($data['category_id'] ?? $oldTx['category_id']);
                $sourceWalletId = (int)($data['source_wallet_id'] ?? $oldTx['source_wallet_id']);
                $cat = OwnershipGuard::assertOwner($this->db, 'categories', $categoryId, $userId);
                if ($cat['type'] !== 'Chi') {
                    throw new InvalidArgumentException('Danh mục không khớp loại Chi.');
                }
                OwnershipGuard::assertOwner($this->db, 'wallets', $sourceWalletId, $userId);
                $this->adjustWalletBalance($sourceWalletId, -$amount, true);
            } else {
                $sourceWalletId = (int)($data['source_wallet_id'] ?? $oldTx['source_wallet_id']);
                $targetWalletId = (int)($data['target_wallet_id'] ?? $oldTx['target_wallet_id']);
                if ($sourceWalletId === $targetWalletId) {
                    throw new InvalidArgumentException('Ví nguồn và ví đích không được trùng nhau.');
                }
                OwnershipGuard::assertOwner($this->db, 'wallets', $sourceWalletId, $userId);
                OwnershipGuard::assertOwner($this->db, 'wallets', $targetWalletId, $userId);
                $this->adjustWalletBalance($sourceWalletId, -$amount, true);
                $this->adjustWalletBalance($targetWalletId, +$amount, false);
            }

            $upd = $this->db->prepare("
                UPDATE transactions
                SET type = ?, amount = ?, transaction_date = ?, category_id = ?,
                    source_wallet_id = ?, target_wallet_id = ?, note = ?, image_url = ?
                WHERE transaction_id = ? AND user_id = ?
            ");
            $upd->execute([
                $type, $amount, $txDate, $categoryId,
                $sourceWalletId, $targetWalletId, $note, $newImageUrl,
                $txId, $userId
            ]);

            if ($type === 'Chi') {
                $this->budgetService->evaluateAfterExpense($userId, $categoryId, $txDate);
            }

            $this->db->commit();
            return $this->getDetail($userId, $txId);
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * SR-3.4: Xóa giao dịch và khôi phục số dư ví
     */
    public function delete(int $userId, int $txId): void {
        $tx = OwnershipGuard::assertOwner($this->db, 'transactions', $txId, $userId);

        $this->db->beginTransaction();
        try {
            $this->revertTransactionEffect($tx);
            $this->db->prepare("DELETE FROM transactions WHERE transaction_id = ? AND user_id = ?")
                     ->execute([$txId, $userId]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * SR-3.1 & SR-12: Danh sách lịch sử phân trang "Xem thêm", lọc đa điều kiện & gom nhóm theo ngày
     */
    public function getHistory(int $userId, array $filters): array {
        $where = ["t.user_id = ?"];
        $params = [$userId];

        if (!empty($filters['search'])) {
            $where[] = "(t.note LIKE ? OR c.name LIKE ?)";
            $kw = '%' . trim($filters['search']) . '%';
            $params[] = $kw;
            $params[] = $kw;
        }
        if (!empty($filters['type']) && in_array($filters['type'], ['Thu', 'Chi', 'Chuyển khoản'], true)) {
            $where[] = "t.type = ?";
            $params[] = $filters['type'];
        }
        if (!empty($filters['category_id'])) {
            $where[] = "t.category_id = ?";
            $params[] = (int)$filters['category_id'];
        }
        if (!empty($filters['wallet_id'])) {
            $wId = (int)$filters['wallet_id'];
            $where[] = "(t.source_wallet_id = ? OR t.target_wallet_id = ?)";
            $params[] = $wId;
            $params[] = $wId;
        }
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(t.transaction_date) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(t.transaction_date) <= ?";
            $params[] = $filters['date_to'];
        }
        if (isset($filters['min_amount']) && $filters['min_amount'] !== '') {
            $where[] = "t.amount >= ?";
            $params[] = (float)$filters['min_amount'];
        }
        if (isset($filters['max_amount']) && $filters['max_amount'] !== '') {
            $where[] = "t.amount <= ?";
            $params[] = (float)$filters['max_amount'];
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db->prepare("
            SELECT COUNT(*) FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.category_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $limit = max(1, min(100, (int)($filters['limit'] ?? 6)));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        $sql = "
            SELECT t.transaction_id, t.type, t.amount, t.transaction_date, t.note, t.image_url,
                   c.category_id, COALESCE(c.name, 'Chuyển khoản nội bộ') AS category_name,
                   COALESCE(c.icon, 'repeat') AS category_icon, COALESCE(c.color, '#64748B') AS category_color,
                   sw.name AS source_wallet_name, tw.name AS target_wallet_name
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.category_id
            LEFT JOIN wallets sw ON t.source_wallet_id = sw.wallet_id
            LEFT JOIN wallets tw ON t.target_wallet_id = tw.wallet_id
            WHERE {$whereSql}
            ORDER BY t.transaction_date DESC, t.transaction_id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Gom nhóm theo ngày (YYYY-MM-DD) đúng theo SR-12
        $grouped = [];
        foreach ($rows as $row) {
            $dateKey = substr($row['transaction_date'], 0, 10);
            $grouped[$dateKey][] = $row;
        }

        return [
            'total'       => $total,
            'offset'      => $offset,
            'limit'       => $limit,
            'has_more'    => ($offset + count($rows)) < $total,
            'grouped'     => $grouped,
            'items'       => $rows,
        ];
    }

    /**
     * SR-3.2 & SR-18: Xem chi tiết giao dịch
     */
    public function getDetail(int $userId, int $txId): array {
        OwnershipGuard::assertOwner($this->db, 'transactions', $txId, $userId);

        $stmt = $this->db->prepare("
            SELECT t.*, CONCAT('#TXN-', t.transaction_id) AS transaction_code,
                   COALESCE(c.name, 'Chuyển khoản nội bộ') AS category_name,
                   c.icon AS category_icon, c.color AS category_color,
                   sw.name AS source_wallet_name, tw.name AS target_wallet_name
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.category_id
            LEFT JOIN wallets sw ON t.source_wallet_id = sw.wallet_id
            LEFT JOIN wallets tw ON t.target_wallet_id = tw.wallet_id
            WHERE t.transaction_id = ? AND t.user_id = ?
        ");
        $stmt->execute([$txId, $userId]);
        return $stmt->fetch();
    }

    private function adjustWalletBalance(int $walletId, float $delta, bool $checkSufficient): void {
        $stmt = $this->db->prepare("SELECT current_balance, name FROM wallets WHERE wallet_id = ? FOR UPDATE");
        $stmt->execute([$walletId]);
        $wallet = $stmt->fetch();

        if ($checkSufficient && ((float)$wallet['current_balance'] + $delta) < 0) {
            throw new RuntimeException("Số dư ví '{$wallet['name']}' không đủ để thực hiện giao dịch.");
        }

        $this->db->prepare("UPDATE wallets SET current_balance = current_balance + ? WHERE wallet_id = ?")
                 ->execute([$delta, $walletId]);
    }

    private function revertTransactionEffect(array $tx): void {
        $amt = (float)$tx['amount'];
        if ($tx['type'] === 'Thu' && $tx['target_wallet_id']) {
            $this->adjustWalletBalance((int)$tx['target_wallet_id'], -$amt, false);
        } elseif ($tx['type'] === 'Chi' && $tx['source_wallet_id']) {
            $this->adjustWalletBalance((int)$tx['source_wallet_id'], +$amt, false);
        } elseif ($tx['type'] === 'Chuyển khoản') {
            if ($tx['source_wallet_id']) {
                $this->adjustWalletBalance((int)$tx['source_wallet_id'], +$amt, false);
            }
            if ($tx['target_wallet_id']) {
                $this->adjustWalletBalance((int)$tx['target_wallet_id'], -$amt, false);
            }
        }
    }
}