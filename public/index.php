<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Utils/Response.php';
require_once __DIR__ . '/../src/Utils/JwtHelper.php';
require_once __DIR__ . '/../src/Common/AuthGuard.php';
require_once __DIR__ . '/../src/Common/OwnershipGuard.php';
require_once __DIR__ . '/../src/Common/StorageService.php';
require_once __DIR__ . '/../src/Common/MailService.php';
require_once __DIR__ . '/../src/Modules/Identity/UserProfileService.php';
require_once __DIR__ . '/../src/Modules/Identity/AuthService.php';
require_once __DIR__ . '/../src/Modules/Finance/WalletService.php';
require_once __DIR__ . '/../src/Modules/Finance/CategoryService.php';
require_once __DIR__ . '/../src/Modules/Finance/BudgetService.php';
require_once __DIR__ . '/../src/Modules/Finance/TransactionService.php';
require_once __DIR__ . '/../src/Modules/Analytics/DashboardReportService.php';
require_once __DIR__ . '/../src/Modules/Analytics/ExportService.php';
require_once __DIR__ . '/../src/Modules/Analytics/CmsAndNotificationService.php';
require_once __DIR__ . '/../src/Modules/Admin/AdminService.php';

$db = Database::getConnection();
$profileService     = new UserProfileService($db);
$authService        = new AuthService($db, $profileService);
$walletService      = new WalletService($db);
$categoryService    = new CategoryService($db);
$budgetService      = new BudgetService($db);
$transactionService = new TransactionService($db, $budgetService);
$reportService      = new DashboardReportService($db, $budgetService);
$exportService      = new ExportService($db);
$cmsService         = new CmsAndNotificationService($db);
$adminService       = new AdminService($db);

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = dirname($_SERVER['SCRIPT_NAME']);
$route = '/' . trim(substr($uri, strlen($basePath)), '/');

$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);
if (!is_array($input)) {
    $input = $_POST;
}

try {
    // ================= 1. PUBLIC CMS (SR-01) =================
    if ($method === 'GET' && $route === '/api/public/landing') {
        Response::json(200, true, 'OK', $cmsService->getLandingPageContent());
    }
    if ($method === 'GET' && preg_match('#^/api/public/articles/(\d+)$#', $route, $m)) {
        Response::json(200, true, 'OK', $cmsService->getArticleDetail((int)$m[1]));
    }

    // ================= 2. AUTH & PROFILE (SR-02 -> SR-08) =================
    if ($method === 'POST' && $route === '/api/auth/register') {
        $res = $authService->register($input);
        $adminService->writeLog($res['user_id'], 'USER_REGISTER', "User#{$res['user_id']}", 'Đăng ký tài khoản mới');
        Response::json(201, true, 'Đăng ký thành công. Vui lòng kiểm tra email để xác thực.', $res);
    }
    if ($method === 'POST' && $route === '/api/auth/resend-otp') {
        $authService->resendVerificationOtp($input['email'] ?? '');
        Response::json(200, true, 'Đã gửi lại mã xác thực.');
    }
    if ($method === 'POST' && $route === '/api/auth/verify-email') {
        $authService->verifyEmail($input['email'] ?? '', $input['otp'] ?? '');
        Response::json(200, true, 'Kích hoạt tài khoản thành công.');
    }
    if ($method === 'POST' && $route === '/api/auth/login') {
        $res = $authService->login($input['email'] ?? '', $input['password'] ?? '', (bool)($input['remember_me'] ?? false));
        $adminService->writeLog($res['user']['user_id'], 'USER_LOGIN', "User#{$res['user']['user_id']}", 'Đăng nhập hệ thống');
        Response::json(200, true, 'Đăng nhập thành công.', $res);
    }
    if ($method === 'POST' && $route === '/api/auth/google') {
        Response::json(200, true, 'Đăng nhập Google thành công.', $authService->loginWithGoogle($input['id_token'] ?? ''));
    }
    if ($method === 'POST' && $route === '/api/auth/forgot-password') {
        $authService->forgotPassword($input['email'] ?? '');
        Response::json(200, true, 'Đã gửi mã OTP khôi phục mật khẩu nếu email tồn tại.');
    }
    if ($method === 'POST' && $route === '/api/auth/reset-password') {
        $authService->resetPassword($input['email'] ?? '', $input['otp'] ?? '', $input['new_password'] ?? '');
        Response::json(200, true, 'Đặt lại mật khẩu thành công.');
    }
    if ($method === 'GET' && $route === '/api/profile') {
        $user = AuthGuard::authenticate($db);
        Response::json(200, true, 'OK', $profileService->getProfile((int)$user['user_id']));
    }
    if ($method === 'PUT' && $route === '/api/profile') {
        $user = AuthGuard::authenticate($db);
        Response::json(200, true, 'Cập nhật hồ sơ thành công.', $profileService->updateProfile((int)$user['user_id'], $input));
    }

    // ================= 3. WALLETS (SR-09) =================
    if ($route === '/api/wallets') {
        $user = AuthGuard::authenticate($db);
        AuthGuard::requireRole($user, 'User');
        if ($method === 'GET') {
            Response::json(200, true, 'OK', $walletService->getAll((int)$user['user_id']));
        }
        if ($method === 'POST') {
            Response::json(201, true, 'Tạo ví thành công.', $walletService->create((int)$user['user_id'], $input));
        }
    }
    if (preg_match('#^/api/wallets/(\d+)$#', $route, $m)) {
        $user = AuthGuard::authenticate($db);
        AuthGuard::requireRole($user, 'User');
        $wId = (int)$m[1];
        if ($method === 'PUT') {
            Response::json(200, true, 'Cập nhật ví thành công.', $walletService->update((int)$user['user_id'], $wId, $input));
        }
        if ($method === 'DELETE') {
            $mode = $_GET['mode'] ?? ($input['mode'] ?? 'DELETE');
            $msg = $walletService->deleteOrHide((int)$user['user_id'], $wId, $mode);
            Response::json(200, true, $msg);
        }
    }

    // ================= 4. CATEGORIES (SR-13) =================
    if ($route === '/api/categories') {
        $user = AuthGuard::authenticate($db);
        AuthGuard::requireRole($user, 'User');
        if ($method === 'GET') {
            Response::json(200, true, 'OK', $categoryService->getUserCategories(
                (int)$user['user_id'],
                $_GET['type'] ?? null,
                $_GET['search'] ?? ''
            ));
        }
        if ($method === 'POST') {
            Response::json(201, true, 'Thêm danh mục thành công.', $categoryService->create((int)$user['user_id'], $input));
        }
    }
    if (preg_match('#^/api/categories/(\d+)$#', $route, $m)) {
        $user = AuthGuard::authenticate($db);
        AuthGuard::requireRole($user, 'User');
        $cId = (int)$m[1];
        if ($method === 'PUT') {
            Response::json(200, true, 'Cập nhật danh mục thành công.', $categoryService->update((int)$user['user_id'], $cId, $input));
        }
        if ($method === 'DELETE') {
            $mode = $_GET['mode'] ?? ($input['mode'] ?? 'DELETE');
            $targetId = isset($_GET['target_category_id']) ? (int)$_GET['target_category_id'] : ($input['target_category_id'] ?? null);
            $msg = $categoryService->deleteOrMerge((int)$user['user_id'], $cId, $mode, $targetId ? (int)$targetId : null);
            Response::json(200, true, $msg);
        }
    }

    // ================= 5. TRANSACTIONS (SR-10, SR-12, SR-18) =================
    if ($route === '/api/transactions') {
        $user = AuthGuard::authenticate($db);
        AuthGuard::requireRole($user, 'User');
        if ($method === 'GET') {
            Response::json(200, true, 'OK', $transactionService->getHistory((int)$user['user_id'], $_GET));
        }
        if ($method === 'POST') {
            // Hỗ trợ cả JSON lẫn multipart/form-data (upload ảnh hóa đơn)
            $tx = $transactionService->create((int)$user['user_id'], $input, $_FILES['receipt'] ?? null);
            Response::json(201, true, 'Lưu giao dịch thành công.', $tx);
        }
    }
    if (preg_match('#^/api/transactions/(\d+)$#', $route, $m)) {
        $user = AuthGuard::authenticate($db);
        AuthGuard::requireRole($user, 'User');
        $txId = (int)$m[1];
        if ($method === 'GET') {
            Response::json(200, true, 'OK', $transactionService->getDetail((int)$user['user_id'], $txId));
        }
        // Dùng POST kèm _method=PUT nếu client gửi multipart/form-data cập nhật ảnh
        if ($method === 'PUT' || ($method === 'POST' && ($input['_method'] ?? '') === 'PUT')) {
            $tx = $transactionService->update((int)$user['user_id'], $txId, $input, $_FILES['receipt'] ?? null);
            Response::json(200, true, 'Cập nhật giao dịch thành công.', $tx);
        }
        if ($method === 'DELETE') {
            $transactionService->delete((int)$user['user_id'], $txId);
            Response::json(200, true, 'Đã xóa giao dịch và hoàn tác số dư ví.');
        }
    }

    // ================= 6. BUDGETS & NOTIFICATIONS (SR-14) =================
    if ($route === '/api/budgets') {
        $user = AuthGuard::authenticate($db);
        AuthGuard::requireRole($user, 'User');
        if ($method === 'GET') {
            Response::json(200, true, 'OK', $budgetService->getCurrentBudgets((int)$user['user_id']));
        }
        if ($method === 'POST') {
            Response::json(201, true, 'Thiết lập ngân sách thành công.', $budgetService->create((int)$user['user_id'], $input));
        }
    }
    if (preg_match('#^/api/budgets/(\d+)$#', $route, $m)) {
        $user = AuthGuard::authenticate($db);
        AuthGuard::requireRole($user, 'User');
        $bId = (int)$m[1];
        if ($method === 'PUT') {
            Response::json(200, true, 'Cập nhật ngân sách thành công.', $budgetService->update((int)$user['user_id'], $bId, $input));
        }
        if ($method === 'DELETE') {
            $budgetService->delete((int)$user['user_id'], $bId);
            Response::json(200, true, 'Đã xóa ngân sách.');
        }
    }
    if ($method === 'GET' && $route === '/api/notifications') {
        $user = AuthGuard::authenticate($db);
        Response::json(200, true, 'OK', $cmsService->getUserNotifications((int)$user['user_id']));
    }
    if ($method === 'PUT' && preg_match('#^/api/notifications/(\d+)/read$#', $route, $m)) {
        $user = AuthGuard::authenticate($db);
        $cmsService->markNotificationRead((int)$user['user_id'], (int)$m[1]);
        Response::json(200, true, 'Đã đánh dấu đã đọc.');
    }

    // ================= 7. DASHBOARD, REPORTS & EXPORT (SR-10, SR-11, SR-15) =================
    if ($method === 'GET' && $route === '/api/dashboard') {
        $user = AuthGuard::authenticate($db);
        AuthGuard::requireRole($user, 'User');
        $month = $_GET['month'] ?? date('Y-m');
        Response::json(200, true, 'OK', $reportService->getDashboard((int)$user['user_id'], $month));
    }
    if ($method === 'GET' && $route === '/api/reports/cashflow') {
        $user = AuthGuard::authenticate($db);
        AuthGuard::requireRole($user, 'User');
        $from = $_GET['date_from'] ?? date('Y-m-01');
        $to   = $_GET['date_to'] ?? date('Y-m-t');
        Response::json(200, true, 'OK', $reportService->getCashflowReport((int)$user['user_id'], $from, $to));
    }
    if ($method === 'GET' && $route === '/api/reports/breakdown') {
        $user = AuthGuard::authenticate($db);
        AuthGuard::requireRole($user, 'User');
        $from = $_GET['date_from'] ?? date('Y-m-01');
        $to   = $_GET['date_to'] ?? date('Y-m-t');
        $type = $_GET['type'] ?? 'Chi';
        Response::json(200, true, 'OK', $reportService->getExpenseBreakdown((int)$user['user_id'], $from, $to, $type));
    }
    if ($method === 'GET' && $route === '/api/reports/export') {
        $user = AuthGuard::authenticate($db);
        AuthGuard::requireRole($user, 'User');
        $from = $_GET['date_from'] ?? date('Y-m-01');
        $to   = $_GET['date_to'] ?? date('Y-m-t');
        $exportService->downloadTransactionsCsv((int)$user['user_id'], $from, $to);
    }

    // ================= 8. ADMIN PANEL (SR-19) =================
    if (str_starts_with($route, '/api/admin/')) {
        $admin = AuthGuard::authenticate($db);
        AuthGuard::requireRole($admin, 'Admin');
        $adminId = (int)$admin['user_id'];

        if ($method === 'GET' && $route === '/api/admin/dashboard') {
            Response::json(200, true, 'OK', $adminService->getAdminDashboard());
        }
        if ($method === 'GET' && $route === '/api/admin/users') {
            Response::json(200, true, 'OK', $adminService->getUsersList($_GET['search'] ?? '', $_GET['status'] ?? null));
        }
        if ($method === 'PUT' && preg_match('#^/api/admin/users/(\d+)/lock$#', $route, $m)) {
            $adminService->toggleUserLock($adminId, (int)$m[1], (bool)($input['lock'] ?? true));
            Response::json(200, true, 'Cập nhật trạng thái tài khoản thành công.');
        }
        if ($route === '/api/admin/master-categories') {
            if ($method === 'GET') {
                Response::json(200, true, 'OK', $adminService->getMasterCategories());
            }
            if ($method === 'POST') {
                $adminService->createMasterCategory($adminId, $input);
                Response::json(201, true, 'Đã thêm danh mục mặc định mới.');
            }
        }
        if ($method === 'PUT' && preg_match('#^/api/admin/master-categories/(\d+)$#', $route, $m)) {
            $adminService->updateMasterCategory($adminId, (int)$m[1], $input);
            Response::json(200, true, 'Đã cập nhật danh mục mặc định.');
        }
    }

    Response::json(404, false, 'Endpoint không tồn tại: ' . $route);

} catch (InvalidArgumentException | RuntimeException $e) {
    Response::json(400, false, $e->getMessage());
} catch (Throwable $e) {
    Response::json(500, false, 'Lỗi máy chủ: ' . $e->getMessage());
}