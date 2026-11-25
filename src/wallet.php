<?php
declare(strict_types=1);

class WalletService {
    public function __construct(private PDO $db) {}

    public function getAll(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT wallet_id, name, icon, initial_balance, current_balance, created_at
            FROM wallets
            WHERE user_id = ? AND is_hidden = 0
            ORDER BY wallet_id ASC
        ");
        $stmt->execute([$userId]);
        $wallets = $stmt->fetchAll();

        $totalBalance = array_reduce(
            $wallets,
            fn(float $sum, array $w) => $sum + (float)$w['current_balance'],
            0.0
        );

        return [
            'total_balance' => $totalBalance,
            'wallets'       => $wallets,
        ];
    }

    public function create(int $userId, array $data): array {
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('Tên ví không được để trống.');
        }

        $icon = trim($data['icon'] ?? 'wallet');
        $initialBalance = (float)($data['initial_balance'] ?? 0);
        if ($initialBalance < 0) {
            throw new InvalidArgumentException('Số dư ban đầu không được âm.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO wallets (user_id, name, icon, initial_balance, current_balance)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $name, $icon, $initialBalance, $initialBalance]);
        $id = (int)$this->db->lastInsertId();

        return OwnershipGuard::assertOwner($this->db, 'wallets', $id, $userId);
    }

    public function update(int $userId, int $walletId, array $data): array {
        $wallet = OwnershipGuard::assertOwner($this->db, 'wallets', $walletId, $userId);

        $name = trim($data['name'] ?? $wallet['name']);
        if ($name === '') {
            throw new InvalidArgumentException('Tên ví không được để trống.');
        }
        $icon = trim($data['icon'] ?? $wallet['icon']);
        $currentBalance = isset($data['current_balance'])
            ? (float)$data['current_balance']
            : (float)$wallet['current_balance'];

        $stmt = $this->db->prepare("
            UPDATE wallets SET name = ?, icon = ?, current_balance = ?
            WHERE wallet_id = ? AND user_id = ?
        ");
        $stmt->execute([$name, $icon, $currentBalance, $walletId, $userId]);

        return OwnershipGuard::assertOwner($this->db, 'wallets', $walletId, $userId);
    }

    /**
     * Xóa ví nếu trống; nếu đã có giao dịch thì chặn xóa cứng hoặc ẩn/lưu trữ (mode = 'HIDE')
     */
    public function deleteOrHide(int $userId, int $walletId, string $mode = 'DELETE'): string {
        OwnershipGuard::assertOwner($this->db, 'wallets', $walletId, $userId);

        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM transactions
            WHERE user_id = ? AND (source_wallet_id = ? OR target_wallet_id = ?)
        ");
        $stmt->execute([$userId, $walletId, $walletId]);
        $txCount = (int)$stmt->fetchColumn();

        if ($txCount === 0 && $mode === 'DELETE') {
            $this->db->prepare("DELETE FROM wallets WHERE wallet_id = ? AND user_id = ?")
                     ->execute([$walletId, $userId]);
            return 'Đã xóa ví vĩnh viễn.';
        }

        if ($mode === 'HIDE') {
            $this->db->prepare("UPDATE wallets SET is_hidden = 1 WHERE wallet_id = ? AND user_id = ?")
                     ->execute([$walletId, $userId]);
            return 'Ví đã được ẩn/lưu trữ để bảo toàn dữ liệu thống kê cũ.';
        }

        throw new RuntimeException('Ví này đã phát sinh giao dịch nên không thể xóa vĩnh viễn. Vui lòng chọn "Ẩn/Lưu trữ ví".');
    }
}