const forgotPasswordForm = document.getElementById("forgotPasswordForm");
  if (forgotPasswordForm) {
    forgotPasswordForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const emailInput = document.getElementById("forgotEmail");
      const email = emailInput ? emailInput.value.trim() : "";

      if (!email) {
        showMessage("forgotMessage", "Vui lòng nhập email đã đăng ký tài khoản.", "error");
        return;
      }

      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        showMessage("forgotMessage", "Định dạng email chưa hợp lệ.", "error");
        return;
      }

      localStorage.setItem("tram_reset_email", email);
      showMessage(
        "forgotMessage",
        `Đã gửi mã OTP / liên kết khôi phục tới ${email}. Đang chuyển sang trang đặt lại mật khẩu...`,
        "success"
      );

      setTimeout(() => {
        window.location.href = "reset-password.html";
      }, 900);
    });
  }