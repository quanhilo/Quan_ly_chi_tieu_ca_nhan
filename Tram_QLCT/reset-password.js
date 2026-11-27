document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("resetForm");
  const otpInput = document.getElementById("otpInput");
  const newPassInput = document.getElementById("newPasswordInput");
  const confirmPassInput = document.getElementById("confirmNewPasswordInput");
  const feedback = document.getElementById("resetFeedback");

  function setMessage(msg, isSuccess = false) {
    if (!feedback) return;
    feedback.textContent = msg;
    feedback.classList.toggle("success", isSuccess);
  }

  if (form) {
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const otp = (otpInput?.value || "").trim();
      const newPassword = newPassInput?.value || "";
      const confirmPassword = confirmPassInput?.value || "";

      if (otp.length < 4) {
        setMessage("Vui lòng nhập mã xác thực OTP hợp lệ.");
        return;
      }

      if (newPassword.length < 6) {
        setMessage("Mật khẩu mới phải có ít nhất 6 ký tự.");
        return;
      }

      if (newPassword !== confirmPassword) {
        setMessage("Mật khẩu mới nhập lại không khớp.");
        return;
      }

      try {
        localStorage.setItem("tram_password_reset_done", "true");
        localStorage.removeItem("tram_reset_email");
      } catch (_) {}

      setMessage("Đặt lại mật khẩu thành công! Đang chuyển về Đăng nhập...", true);
      window.setTimeout(() => {
        window.location.href = "login.html";
      }, 700);
    });
  }
});