<?php
declare(strict_types=1);

class AuthService {
    public function __construct(
        private PDO $db,
        private UserProfileService $profileService
    ) {}

    public function register(array $data): array {
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';
        $fullName = trim($data['full_name'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Địa chỉ email không hợp lệ.');
        }
        if (strlen($password) < 6) {
            throw new InvalidArgumentException('Mật khẩu phải có ít nhất 6 ký tự.');
        }
        if ($fullName === '') {
            throw new InvalidArgumentException('Vui lòng nhập họ và tên.');
        }

        // Kiểm tra email tồn tại
        $check = $this->db->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            throw new RuntimeException('Email này đã được đăng ký.');
        }

        $this->db->beginTransaction();
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("
                INSERT INTO users (email, password_hash, full_name, role, status)
                VALUES (?, ?, ?, 'User', 'Pending_Verification')
            ");
            $stmt->execute([$email, $hash, $fullName]);
            $userId = (int)$this->db->lastInsertId();

            // Khởi tạo vùng dữ liệu và nhân bản bộ danh mục mặc định
            $this->profileService->initializeUserWorkspace($userId);

            // Tạo mã OTP xác thực email
            $otp = $this->issueOtp($userId, 'VERIFY_EMAIL');
            $this->db->commit();

            MailService::sendOtp($email, 'Xác thực tài khoản PersonalFinance', $otp, 'kích hoạt tài khoản');
            return ['user_id' => $userId, 'email' => $email];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function resendVerificationOtp(string $email): void {
        $email = strtolower(trim($email));
        $stmt = $this->db->prepare("SELECT user_id, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new RuntimeException('Không tìm thấy tài khoản.');
        }
        if ($user['status'] !== 'Pending_Verification') {
            throw new RuntimeException('Tài khoản đã được kích hoạt hoặc đang bị khóa.');
        }

        // Giới hạn gửi lại sau 60 giây
        $lastStmt = $this->db->prepare("
            SELECT UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(created_at) AS elapsed 
            FROM user_tokens 
            WHERE user_id = ? AND type = 'VERIFY_EMAIL' 
            ORDER BY created_at DESC LIMIT 1
        ");
        $lastStmt->execute([$user['user_id']]);
        $last = $lastStmt->fetch();

        if ($last && (int)$last['elapsed'] < 60) {
            $wait = 60 - (int)$last['elapsed'];
            throw new RuntimeException("Vui lòng đợi {$wait} giây trước khi gửi lại mã.");
        }

        $otp = $this->issueOtp((int)$user['user_id'], 'VERIFY_EMAIL');
        MailService::sendOtp($email, 'Gửi lại mã xác thực tài khoản', $otp, 'kích hoạt tài khoản');
    }

    public function verifyEmail(string $email, string $otp): void {
        $email = strtolower(trim($email));
        $stmt = $this->db->prepare("SELECT user_id, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new RuntimeException('Email không tồn tại.');
        }
        $this->validateOtp((int)$user['user_id'], 'VERIFY_EMAIL', $otp);

        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE users SET status = 'Active' WHERE user_id = ?")->execute([$user['user_id']]);
            $this->db->prepare("DELETE FROM user_tokens WHERE user_id = ? AND type = 'VERIFY_EMAIL'")->execute([$user['user_id']]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function login(string $email, string $password, bool $rememberMe = false): array {
        $email = strtolower(trim($email));
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            throw new RuntimeException('Email hoặc mật khẩu không chính xác.');
        }
        if ($user['status'] === 'Pending_Verification') {
            throw new RuntimeException('Tài khoản chưa xác thực email.');
        }
        if ($user['status'] === 'Locked') {
            throw new RuntimeException('Tài khoản đã bị khóa bởi quản trị viên.');
        }

        // Ghi nhớ đăng nhập: 30 ngày; mặc định: 24 giờ
        $ttl = $rememberMe ? (30 * 86400) : 86400;
        $token = JwtHelper::encode([
            'user_id'       => (int)$user['user_id'],
            'role'          => $user['role'],
            'token_version' => (int)$user['token_version'],
        ], $ttl);

        return [
            'access_token' => $token,
            'expires_in'   => $ttl,
            'user'         => [
                'user_id'   => (int)$user['user_id'],
                'email'     => $user['email'],
                'full_name' => $user['full_name'],
                'role'      => $user['role'],
                'currency'  => $user['currency'],
            ]
        ];
    }

    public function loginWithGoogle(string $idToken): array {
        $verifyUrl = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($idToken);
        $raw = @file_get_contents($verifyUrl);
        if (!$raw) {
            throw new RuntimeException('Không thể xác thực Google Token.');
        }
        $googleData = json_decode($raw, true);
        if (($googleData['aud'] ?? '') !== GOOGLE_CLIENT_ID || empty($googleData['email'])) {
            throw new RuntimeException('Google Token không hợp lệ.');
        }

        $email = strtolower($googleData['email']);
        $googleId = $googleData['sub'];
        $fullName = $googleData['name'] ?? explode('@', $email)[0];

        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->db->beginTransaction();
            try {
                $ins = $this->db->prepare("
                    INSERT INTO users (email, google_id, full_name, role, status)
                    VALUES (?, ?, ?, 'User', 'Active')
                ");
                $ins->execute([$email, $googleId, $fullName]);
                $userId = (int)$this->db->lastInsertId();

                $this->profileService->initializeUserWorkspace($userId);
                $this->db->commit();

                $stmt->execute([$email]);
                $user = $stmt->fetch();
            } catch (Throwable $e) {
                $this->db->rollBack();
                throw $e;
            }
        } elseif ($user['status'] === 'Locked') {
            throw new RuntimeException('Tài khoản đã bị khóa.');
        }

        $ttl = 7 * 86400;
        $token = JwtHelper::encode([
            'user_id'       => (int)$user['user_id'],
            'role'          => $user['role'],
            'token_version' => (int)$user['token_version'],
        ], $ttl);

        return ['access_token' => $token, 'expires_in' => $ttl];
    }

    public function forgotPassword(string $email): void {
        $email = strtolower(trim($email));
        $stmt = $this->db->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            return; // Tránh dò quét email tồn tại
        }

        $otp = $this->issueOtp((int)$user['user_id'], 'RESET_PASSWORD');
        MailService::sendOtp($email, 'Khôi phục mật khẩu PersonalFinance', $otp, 'đặt lại mật khẩu');
    }

    public function resetPassword(string $email, string $otp, string $newPassword): void {
        if (strlen($newPassword) < 6) {
            throw new InvalidArgumentException('Mật khẩu mới phải có ít nhất 6 ký tự.');
        }

        $email = strtolower(trim($email));
        $stmt = $this->db->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new RuntimeException('Yêu cầu không hợp lệ.');
        }

        $this->validateOtp((int)$user['user_id'], 'RESET_PASSWORD', $otp);

        $this->db->beginTransaction();
        try {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            // Tăng token_version + 1 để hủy toàn bộ phiên đăng nhập cũ
            $upd = $this->db->prepare("
                UPDATE users 
                SET password_hash = ?, token_version = token_version + 1, status = 'Active'
                WHERE user_id = ?
            ");
            $upd->execute([$newHash, $user['user_id']]);

            $this->db->prepare("DELETE FROM user_tokens WHERE user_id = ? AND type = 'RESET_PASSWORD'")
                     ->execute([$user['user_id']]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function issueOtp(int $userId, string $type): string {
        $otp = (string)random_int(100000, 999999);
        $hash = password_hash($otp, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("
            INSERT INTO user_tokens (user_id, type, token_hash, expires_at)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
        ");
        $stmt->execute([$userId, $type, $hash]);
        return $otp;
    }

    private function validateOtp(int $userId, string $type, string $otp): void {
        $stmt = $this->db->prepare("
            SELECT token_hash, expires_at 
            FROM user_tokens 
            WHERE user_id = ? AND type = ? 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$userId, $type]);
        $record = $stmt->fetch();

        if (!$record || strtotime($record['expires_at']) < time() || !password_verify($otp, $record['token_hash'])) {
            throw new RuntimeException('Mã OTP không chính xác hoặc đã hết hạn.');
        }
    }
}