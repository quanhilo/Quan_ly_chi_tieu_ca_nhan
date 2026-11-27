document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("registerForm");
  const emailInput = document.getElementById("regEmail");
  const passwordInput = document.getElementById("regPassword");
  const confirmInput = document.getElementById("regConfirmPassword");
  const feedback = document.getElementById("registerFeedback");
  const googleBtn = document.getElementById("googleRegisterBtn");

  function setMessage(msg, isSuccess = false) {
    if (!feedback) return;
    feedback.textContent = msg;
    feedback.classList.toggle("success", isSuccess);
  }

  if (form) {
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const email = (emailInput?.value || "").trim();
      const password = passwordInput?.value || "";
      const confirmPassword = confirmInput?.value || "";

      if (!email || !email.includes("@")) {
        setMessage("Vui lòng nhập địa chỉ Email hợp lệ.");
        return;
      }

      if (password.length < 6) {
        setMessage("Mật khẩu phải có ít nhất 6 ký tự.");
        return;
      }

      if (password !== confirmPassword) {
        setMessage("Mật khẩu nhập lại không khớp.");
        return;
      }

      const fallbackName = email.split("@")[0] || "Người dùng";
      const userRecord = {
        email,
        fullname: fallbackName
      };

      try {
        localStorage.setItem("tram_registered_user", JSON.stringify(userRecord));
        localStorage.setItem("tram_logged_in_user", JSON.stringify(userRecord));
      } catch (_) {}

      setMessage("Đăng ký thành công! Đang chuyển hướng...", true);
      window.setTimeout(() => {
        window.location.href = "index.html";
      }, 600);
    });
  }

  if (googleBtn) {
    googleBtn.addEventListener("click", () => {
      const googleUser = {
        email: "nguyenvana@gmail.com",
        fullname: "Nguyễn Văn A"
      };
      try {
        localStorage.setItem("tram_logged_in_user", JSON.stringify(googleUser));
      } catch (_) {}
      setMessage("Đã liên kết tài khoản Google. Đang chuyển hướng...", true);
      window.setTimeout(() => {
        window.location.href = "index.html";
      }, 500);
    });
  }
});