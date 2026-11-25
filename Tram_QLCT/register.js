const registerForm = document.getElementById("registerForm");
  if (registerForm) {
    registerForm.addEventListener("submit", (e) => {
      e.preventDefault();
      clearMessage("authMessage");

      const email = document.getElementById("email").value.trim();
      const password = document.getElementById("password").value;
      const confirmPassword = document.getElementById("confirmPassword").value;

      if (!email || !password || !confirmPassword) {
        showMessage("authMessage", "Vui lòng nhập đầy đủ Email và mật khẩu.");
        return;
      }

      if (password.length < 6) {
        showMessage("authMessage", "Mật khẩu phải có ít nhất 6 ký tự.");
        return;
      }

      if (password !== confirmPassword) {
        showMessage("authMessage", "Mật khẩu nhập lại không khớp.");
        return;
      }

      const defaultName = email.split("@")[0];
      localStorage.setItem(
        "tram_registered_user",
        JSON.stringify({ fullname: defaultName, email, password, verified: false })
      );

      showMessage(
        "authMessage",
        "Đăng ký thành công! Đang chuyển tới trang xác thực...",
        "success"
      );

      setTimeout(() => {
        window.location.href = "verify.html";
      }, 900);
    });
  }

  const googleRegisterBtn = document.getElementById("googleRegisterBtn");
  if (googleRegisterBtn) {
    googleRegisterBtn.addEventListener("click", () => {
      const googleUser = {
        fullname: "Nguyễn Thị Trâm",
        email: "tram.google@qlct.vn",
        password: "",
        verified: true
      };
      localStorage.setItem("tram_registered_user", JSON.stringify(googleUser));
      localStorage.setItem("tram_logged_in_user", JSON.stringify(googleUser));
      showMessage("authMessage", "Đăng ký bằng Google thành công!", "success");
      setTimeout(() => {
        window.location.href = "profile.html";
      }, 800);
    });
  }