-- 1. Tạo cơ sở dữ liệu
CREATE DATABASE IF NOT EXISTS personal_finance
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE personal_finance;

-- 2. Bảng Người dùng (Users)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NULL,
    gender ENUM('Nam', 'Nữ', 'Khác') NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'VND',
    role ENUM('User', 'Admin') NOT NULL DEFAULT 'User',
    status ENUM('Pending_Verification', 'Active', 'Locked') NOT NULL DEFAULT 'Pending_Verification',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Bảng Ví / Nguồn tiền (Wallets)
CREATE TABLE wallets (
    wallet_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(100) NULL,
    initial_balance DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    current_balance DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_wallets_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Bảng Danh mục Thu - Chi (Categories)
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'NULL = Danh mục mặc định hệ thống do Admin tạo',
    name VARCHAR(100) NOT NULL,
    type ENUM('Thu', 'Chi') NOT NULL,
    icon VARCHAR(100) NULL,
    color VARCHAR(20) NULL,
    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_categories_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT uq_user_category UNIQUE (user_id, name, type)
) ENGINE=InnoDB;

-- 5. Bảng Giao dịch (Transactions)
CREATE TABLE transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('Thu', 'Chi', 'Chuyển khoản') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    transaction_date DATETIME NOT NULL,
    category_id INT NULL,
    source_wallet_id INT NULL,
    target_wallet_id INT NULL,
    note TEXT NULL,
    image_url VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_transactions_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_transactions_category FOREIGN KEY (category_id)
        REFERENCES categories(category_id) ON DELETE RESTRICT,
    CONSTRAINT fk_transactions_source_wallet FOREIGN KEY (source_wallet_id)
        REFERENCES wallets(wallet_id) ON DELETE RESTRICT,
    CONSTRAINT fk_transactions_target_wallet FOREIGN KEY (target_wallet_id)
        REFERENCES wallets(wallet_id) ON DELETE RESTRICT,
    CONSTRAINT chk_transaction_amount CHECK (amount > 0),
    CONSTRAINT chk_transaction_logic CHECK (
        (type = 'Thu' AND target_wallet_id IS NOT NULL AND source_wallet_id IS NULL AND category_id IS NOT NULL)
        OR
        (type = 'Chi' AND source_wallet_id IS NOT NULL AND target_wallet_id IS NULL AND category_id IS NOT NULL)
        OR
        (type = 'Chuyển khoản' AND source_wallet_id IS NOT NULL AND target_wallet_id IS NOT NULL AND source_wallet_id <> target_wallet_id AND category_id IS NULL)
    )
) ENGINE=InnoDB;

-- 6. Bảng Ngân sách (Budgets)
CREATE TABLE budgets (
    budget_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NULL COMMENT 'NULL = Áp dụng cho Tất cả khoản chi',
    amount_limit DECIMAL(15, 2) NOT NULL,
    period ENUM('Hàng tuần', 'Hàng tháng') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_budgets_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_budgets_category FOREIGN KEY (category_id)
        REFERENCES categories(category_id) ON DELETE CASCADE,
    CONSTRAINT chk_budget_limit CHECK (amount_limit > 0)
) ENGINE=InnoDB;

-- 7. Bảng Nhật ký hệ thống (Audit_Logs)
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    target_entity VARCHAR(100) NULL,
    details TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 8. Chỉ mục (Index) tăng tốc độ truy vấn báo cáo và lọc dữ liệu
CREATE INDEX idx_transactions_user_date ON transactions(user_id, transaction_date);
CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at);

-- 9. Dữ liệu mẫu: Danh mục mặc định của hệ thống (user_id = NULL)
INSERT INTO categories (user_id, name, type, icon, color) VALUES
(NULL, 'Ăn uống', 'Chi', 'utensils', '#FF6B6B'),
(NULL, 'Di chuyển', 'Chi', 'car', '#4ECDC4'),
(NULL, 'Mua sắm', 'Chi', 'shopping-bag', '#FFD166'),
(NULL, 'Hóa đơn & Tiện ích', 'Chi', 'receipt', '#118AB2'),
(NULL, 'Lương', 'Thu', 'wallet', '#06D6A0'),
(NULL, 'Thưởng & Phụ cấp', 'Thu', 'gift', '#8338EC');
USE personal_finance;

-- 1. Nâng cấp bảng users: hỗ trợ Google OAuth & hủy toàn bộ phiên cũ qua token_version
ALTER TABLE users
    MODIFY COLUMN password_hash VARCHAR(255) NULL COMMENT 'NULL nếu đăng ký qua Google OAuth',
    ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER email,
    ADD COLUMN token_version INT NOT NULL DEFAULT 1 COMMENT 'Tăng +1 khi đổi mật khẩu để vô hiệu hóa mọi token cũ' AFTER status;

-- 2. Bảng lưu mã OTP / Token xác thực email & quên mật khẩu (hỗ trợ giới hạn gửi lại 60s)
CREATE TABLE user_tokens (
    token_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('VERIFY_EMAIL', 'RESET_PASSWORD') NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Bảng Thông báo người dùng (Cảnh báo ngân sách 80% / 100%)
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('BUDGET_WARNING', 'BUDGET_EXCEEDED', 'SYSTEM') NOT NULL DEFAULT 'SYSTEM',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Bảng Bài viết / Mẹo tài chính (CMS Trang chủ)
CREATE TABLE articles (
    article_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    summary TEXT NULL,
    content LONGTEXT NOT NULL,
    thumbnail_url VARCHAR(500) NULL,
    views INT NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 5. Bảng Thông báo cập nhật hệ thống công khai (CMS Trang chủ)
CREATE TABLE system_announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;