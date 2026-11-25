const profileForm = document.getElementById("profileForm");
  const logoutBtn = document.getElementById("logoutBtn");

  if (profileForm) {
    const savedProfile = JSON.parse(localStorage.getItem("tram_profile_info") || "null");
    const loggedInUser = JSON.parse(localStorage.getItem("tram_logged_in_user") || "null");

    const fullnameInput = document.getElementById("profileFullname");
    const phoneInput = document.getElementById("profilePhone");
    const genderInput = document.getElementById("profileGender");

    if (fullnameInput) {
      fullnameInput.value = savedProfile?.fullname || loggedInUser?.fullname || "";
    }
    if (phoneInput && savedProfile?.phone) {
      phoneInput.value = savedProfile.phone;
    }
    if (genderInput && savedProfile?.gender) {
      genderInput.value = savedProfile.gender;
    }

    profileForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const fullname = fullnameInput ? fullnameInput.value.trim() : "";
      const phone = phoneInput ? phoneInput.value.trim() : "";
      const gender = genderInput ? genderInput.value.trim() : "";

      if (!fullname || !phone || !gender) {
        showMessage("profileMessage", "Vui lòng nhập đầy đủ Họ và tên, Số điện thoại và Giới tính.", "error");
        return;
      }

      const profileData = { fullname, phone, gender };
      localStorage.setItem("tram_profile_info", JSON.stringify(profileData));
      showMessage("profileMessage", "Lưu hồ sơ thành công! Đang chuyển về trang chủ...", "success");

      setTimeout(() => {
        window.location.href = "index.html";
      }, 900);
    });
  }

  if (logoutBtn) {
    logoutBtn.addEventListener("click", () => {
      localStorage.removeItem("tram_logged_in_user");
      window.location.href = "login.html";
    });
  }