const resendVerifyBtn = document.getElementById("resendVerifyBtn");
  const resendCountdownEl = document.getElementById("resendCountdown");
  if (resendVerifyBtn && resendCountdownEl) {
    let secondsLeft = 60;
    resendCountdownEl.textContent = `${secondsLeft}s`;

    const startTimer = () => {
      resendVerifyBtn.disabled = true;
      const timer = setInterval(() => {
        secondsLeft -= 1;
        if (secondsLeft <= 0) {
          clearInterval(timer);
          resendCountdownEl.textContent = "0s";
          resendVerifyBtn.disabled = false;
        } else {
          resendCountdownEl.textContent = `${secondsLeft}s`;
        }
      }, 1000);
    };

    startTimer();

    resendVerifyBtn.addEventListener("click", () => {
      const registeredUser = getRegisteredUser();
      const targetEmail = registeredUser?.email || "email của bạn";
      showMessage(
        "verifyMessage",
        `Đã gửi lại email xác thực tới ${targetEmail}. Vui lòng kiểm tra hộp thư.`,
        "success"
      );
      secondsLeft = 60;
      resendCountdownEl.textContent = `${secondsLeft}s`;
      startTimer();
    });
  }