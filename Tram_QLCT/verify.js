document.addEventListener("DOMContentLoaded", () => {
  const secondsEl = document.getElementById("secondsLeft");
  const cooldownEl = document.getElementById("cooldownText");
  const resendBtn = document.getElementById("resendBtn");
  const statusEl = document.getElementById("verifyStatus");

  let remaining = 60;
  let timerId = null;

  function startCountdown() {
    remaining = 60;
    if (resendBtn) resendBtn.disabled = true;
    if (cooldownEl) {
      cooldownEl.innerHTML = `Nếu vẫn chưa nhận được gmail, bạn có thể yêu cầu<br />gửi lại mã sau <span id="secondsLeft">${remaining}</span>s`;
    }

    if (timerId) window.clearInterval(timerId);
    timerId = window.setInterval(() => {
      remaining -= 1;
      const currentSpan = document.getElementById("secondsLeft") || secondsEl;
      if (currentSpan) currentSpan.textContent = String(Math.max(0, remaining));

      if (remaining <= 0) {
        window.clearInterval(timerId);
        if (resendBtn) resendBtn.disabled = false;
        if (cooldownEl) {
          cooldownEl.innerHTML = `Bạn có thể yêu cầu gửi lại email xác thực ngay bây giờ.`;
        }
      }
    }, 1000);
  }

  if (resendBtn) {
    resendBtn.addEventListener("click", () => {
      if (resendBtn.disabled) return;
      if (statusEl) {
        statusEl.textContent = "Đã gửi lại email xác thực. Vui lòng kiểm tra hộp thư!";
      }
      startCountdown();
    });
  }

  startCountdown();
});