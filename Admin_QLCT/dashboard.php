<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Bảng điều khiển Quản trị</title>
  <link rel="stylesheet" href="style.css" />
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
  <div class="app-container">
    <!-- SIDEBAR -->
    <aside class="sidebar">
      <div class="brand-title">Quản lý chi tiêu<br />cá nhân</div>
      <nav class="nav-menu">
        <a href="dashboard.php" class="nav-item active">
          <i data-lucide="layout-panel-top"></i>
          <span>Bảng điều khiển<br />Quản trị</span>
        </a>
        <a href="users.php" class="nav-item">
          <i data-lucide="user"></i>
          <span>Quản lý tài khoản<br />user</span>
        </a>
        <a href="categories.php" class="nav-item">
          <i data-lucide="folder"></i>
          <span>Quản lý danh mục<br />mẫu</span>
        </a>
      </nav>
    </aside>

    <!-- NỘI DUNG CHÍNH -->
    <main class="main-content">
      <header class="page-header">
        <h1>Bảng điều khiển Quản trị</h1>
        <p class="subtitle">Xin chào, Quản trị viên | Phiên làm việc bảo mật</p>
      </header>

      <div class="security-banner">
        <i data-lucide="alert-triangle"></i>
        <span>Lưu ý bảo mật: Hệ thống tuyệt đối chặn truy cập vào dữ liệu thu/chi chi tiết người dùng cá nhân</span>
      </div>

      <h2 class="section-heading">Thống kê nền tảng (Các tuyệt đối không có tiền thu/chi)</h2>

      <div class="stats-grid-4">
        <div class="stat-card">
          <div class="stat-label">Tổng số người dùng</div>
          <div class="stat-value" id="stat-total-users">0</div>
          <div class="stat-note">Đang cập nhật</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Tài khoản đang hoạt động</div>
          <div class="stat-value" id="stat-active-users">0</div>
          <div class="stat-note">0% tỉ lệ hoạt động</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Tài khoản bị khoá</div>
          <div class="stat-value" id="stat-locked-users">0</div>
          <div class="stat-note">Tạm ngưng do vi phạm</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Bộ danh mục mặc định</div>
          <div class="stat-value" id="stat-categories">0</div>
          <div class="stat-note">Áp dụng cho user mới</div>
        </div>
      </div>

      <div class="dashboard-bottom-grid">
        <!-- Biểu đồ tăng trưởng -->
        <div class="panel-box chart-panel">
          <h3 class="panel-title">Biểu đồ tăng trưởng người dùng mới</h3>
          <div class="chart-wrapper">
            <div class="y-axis">
              <span>250</span><span>200</span><span>150</span><span>100</span><span>50</span><span>0</span>
            </div>
            <div class="chart-area">
              <div class="grid-lines">
                <div class="line"></div><div class="line"></div><div class="line"></div>
                <div class="line"></div><div class="line"></div><div class="line"></div>
              </div>
              <div class="bars-container" id="chart-bars">
                <!-- Dữ liệu cột biểu đồ sẽ được đổ từ CSDL vào đây -->
              </div>
            </div>
          </div>
        </div>

        <!-- Nhật ký hoạt động -->
        <div class="panel-box log-panel">
          <h3 class="panel-title">Nhật ký hoạt động gần đây</h3>
          <ul class="timeline-list" id="dashboard-logs">
            <!-- Dữ liệu nhật ký sẽ được đổ từ CSDL vào đây -->
            <li class="timeline-item">
              <span class="timeline-dot"></span>
              <div class="timeline-content">
                <div class="log-line-1">Chưa có nhật ký hoạt động nào.</div>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </main>
  </div>

  <!-- POP-UP CHI TIẾT NHẬT KÝ -->
  <div id="log-modal" class="modal-overlay" onclick="if(event.target === this) closeLogModal()">
    <div class="modal-box">
      <div class="modal-header">
        <h3>Chi tiết nhật ký hệ thống</h3>
        <button class="modal-close" onclick="closeLogModal()">×</button>
      </div>
      <div class="modal-body" id="log-modal-content"></div>
      <div class="modal-footer">
        <button class="modal-btn-cancel" onclick="closeLogModal()">Đóng</button>
      </div>
    </div>
  </div>

  <script>
    lucide.createIcons();

    function openLogModal(el) {
      const line1 = el.getAttribute('data-line1') || '';
      const line2 = el.getAttribute('data-line2') || '';
      document.getElementById('log-modal-content').innerHTML = `
        <p><strong>Sự kiện:</strong> ${line1}</p>
        <p><strong>Nội dung:</strong> ${line2}</p>
        <p><strong>Trạng thái bảo mật:</strong> Đã mã hoá, không chứa dữ liệu thu/chi cá nhân.</p>
      `;
      document.getElementById('log-modal').classList.add('show');
    }

    function closeLogModal() {
      document.getElementById('log-modal').classList.remove('show');
    }
  </script>
</body>
</html>