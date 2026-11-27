document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("profileForm");
  const fullNameInput = document.getElementById("fullNameInput");
  const phoneInput = document.getElementById("phoneInput");
  const genderInput = document.getElementById("genderInput");
  const feedback = document.getElementById("profileFeedback");
  const logoutBtn = document.getElementById("logoutTopBtn");

  function setMessage(msg, isSuccess = false) {
    if (!feedback) return;
    feedback.textContent = msg;
    feedback.classList.toggle("success", isSuccess);
  }

  try {
    const existing =
      JSON.parse(localStorage.getItem("tram_user_profile") || "null") ||
      JSON.parse(localStorage.getItem("tram_logged_in_user") || "null");

    if (existing) {
      if (fullNameInput && existing.fullname) fullNameInput.value = existing.fullname;
      if (phoneInput && existing.phone) phoneInput.value = existing.phone;
      if (genderInput && existing.gender) genderInput.value = existing.gender;
    }
  } catch (_) {}

  if (logoutBtn) {
    logoutBtn.addEventListener("click", () => {
      try {
        localStorage.removeItem("tram_logged_in_user");
      } catch (_) {}
    });
  }

  if (form) {
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const fullname = (fullNameInput?.value || "").trim();
      const phone = (phoneInput?.value || "").trim();
      const gender = (genderInput?.value || "").trim();

      if (!fullname) {
        setMessage("Vui lòng nhập Họ và tên.");
        return;
      }

      const digitsOnly = phone.replace(/\D/g, "");
      if (digitsOnly.length < 9 || digitsOnly.length > 12) {
        setMessage("Vui lòng nhập Số điện thoại hợp lệ.");
        return;
      }

      if (!gender) {
        setMessage("Vui lòng nhập hoặc chọn Giới tính.");
        return;
      }

      try {
        const currentLogged = JSON.parse(localStorage.getItem("tram_logged_in_user") || "{}");
        const updatedProfile = {
          ...currentLogged,
          fullname,
          phone,
          gender
        };
        localStorage.setItem("tram_user_profile", JSON.stringify(updatedProfile));
        localStorage.setItem("tram_logged_in_user", JSON.stringify(updatedProfile));
      } catch (_) {}

      setMessage("Lưu hồ sơ thành công! Đang chuyển hướng...", true);
      window.setTimeout(() => {
        window.location.href = "index.html";
      }, 600);
    });
  }
});