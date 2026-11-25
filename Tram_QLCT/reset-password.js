const resetPasswordForm = document.getElementById("resetPasswordForm");
  if (resetPasswordForm) {
    resetPasswordForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const otp = document.getElementById("otpCode")?.value.trim() || "";
      const newPassword = document.getElementById("newPassword")?.value || "";
      const confirmPassword = document.getElementById("confirmNewPassword")?.value || "";

      if (!otp || !newPassword || !confirmPassword) {
        showMessage("resetMessage", "Vui lòng nhập đầy đủ mã OTP và mật khẩu mới.", "error");
        return;
      }

      if (newPassword.length < 6) {
        showMessage("resetMessage", "Mật khẩu mới phải có ít nhất 6 ký tự.", "error");
        return;
      }

      if (newPassword !== confirmPassword) {
        showMessage("resetMessage", "Mật khẩu nhập lại không khớp.", "error");
        return;
      }

      const registeredUser = getRegisteredUser();
      if (registeredUser) {
        registeredUser.password = newPassword;
        localStorage.setItem("tram_registered_user", JSON.stringify(registeredUser));
      }

      localStorage.removeItem("tram_reset_email");
      showMessage(
        "resetMessage",
        "Đặt lại mật khẩu thành công! Đang chuyển về trang đăng nhập...",
        "success"
      );

      setTimeout(() => {
        window.location.href = "login.html";
      }, 900);
    });
  }