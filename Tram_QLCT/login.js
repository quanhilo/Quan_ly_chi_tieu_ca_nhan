document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("loginForm");
  const emailInput = document.getElementById("loginEmail");
  const passwordInput = document.getElementById("loginPassword");
  const rememberBtn = document.getElementById("rememberBtn");
  const forgotLink = document.getElementById("forgotPasswordLink");
  const feedback = document.getElementById("loginFeedback");
  const googleBtn = document.getElementById("googleLoginBtn");

  let remembered = false;

  try {
    const savedEmail = localStorage.getItem("tram_remembered_email");
    if (savedEmail && emailInput) {
      emailInput.value = savedEmail;
      remembered = true;
      if (rememberBtn) rememberBtn.setAttribute("aria-pressed", "true");
    }
  } catch (_) {}

  function setMessage(msg, isSuccess = false) {
    if (!feedback) return;
    feedback.textContent = msg;
    feedback.classList.toggle("success", isSuccess);
  }

  if (rememberBtn) {
    rememberBtn.addEventListener("click", () => {
      remembered = !remembered;
      rememberBtn.setAttribute("aria-pressed", String(remembered));
    });
  }

  if (forgotLink) {
    forgotLink.addEventListener("click", (e) => {
      e.preventDefault();
      const currentEmail = (emailInput?.value || "").trim();
      setMessage(
        currentEmail
          ? `Đã gửi hướng dẫn khôi phục mật khẩu tới ${currentEmail}.`
          : "Vui lòng nhập Email của bạn để nhận hướng dẫn đặt lại mật khẩu.",
        Boolean(currentEmail)
      );
    });
  }

  if (form) {
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const email = (emailInput?.value || "").trim();
      const password = passwordInput?.value || "";

      if (!email || !email.includes("@")) {
        setMessage("Vui lòng nhập địa chỉ Email hợp lệ.");
        return;
      }

      if (!password) {
        setMessage("Vui lòng nhập mật khẩu.");
        return;
      }

      let fullname = "Nguyễn Văn A";
      try {
        const registered = JSON.parse(localStorage.getItem("tram_registered_user") || "null");
        if (registered && registered.email === email && registered.fullname) {
          fullname = registered.fullname;
        } else {
          fullname = email.split("@")[0] || "Nguyễn Văn A";
        }

        localStorage.setItem("tram_logged_in_user", JSON.stringify({ email, fullname }));
        if (remembered) {
          localStorage.setItem("tram_remembered_email", email);
        } else {
          localStorage.removeItem("tram_remembered_email");
        }
      } catch (_) {}

      setMessage("Đăng nhập thành công! Đang chuyển hướng...", true);
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
      setMessage("Đã đăng nhập bằng Google. Đang chuyển hướng...", true);
      window.setTimeout(() => {
        window.location.href = "index.html";
      }, 500);
    });
  }
});