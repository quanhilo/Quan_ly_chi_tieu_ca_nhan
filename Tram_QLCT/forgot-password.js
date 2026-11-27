document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("forgotForm");
  const emailInput = document.getElementById("forgotEmail");
  const feedback = document.getElementById("forgotFeedback");

  function setMessage(msg, isSuccess = false) {
    if (!feedback) return;
    feedback.textContent = msg;
    feedback.classList.toggle("success", isSuccess);
  }

  if (form) {
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const email = (emailInput?.value || "").trim();

      if (!email || !email.includes("@")) {
        setMessage("Vui lòng nhập địa chỉ Email hợp lệ.");
        return;
      }

      try {
        localStorage.setItem("tram_reset_email", email);
      } catch (_) {}

      setMessage(`Đã gửi mã OTP và liên kết khôi phục tới ${email}.`, true);
    });
  }
});