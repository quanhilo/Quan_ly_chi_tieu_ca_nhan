document.addEventListener("DOMContentLoaded", () => {
  // Hiển thị tên người dùng nếu đã lưu trong localStorage
  const greetingEl = document.getElementById("greetingUser");
  try {
    const loggedUser = JSON.parse(localStorage.getItem("tram_logged_in_user") || "null");
    if (loggedUser && loggedUser.fullname && greetingEl) {
      greetingEl.textContent = `Xin chào, ${loggedUser.fullname}!`;
    }
  } catch (_) {}

  // Vẽ biểu đồ cột tóm tắt thu chi theo tuần
  const canvas = document.getElementById("weeklyChart");
  if (canvas && canvas.getContext) {
    const ctx = canvas.getContext("2d");
    const w = canvas.width;
    const h = canvas.height;
    ctx.clearRect(0, 0, w, h);

    const groups = [
      { income: 140, expense: 70 },
      { income: 115, expense: 65 },
      { income: 125, expense: 75 },
      { income: 110, expense: 60 },
      { income: 135, expense: 70 },
      { income: 150, expense: 80 }
    ];

    const baseY = h - 18;
    const startX = 28;
    const stepX = (w - startX * 2) / groups.length;
    const barW = 14;

    // Đường kẻ ngang mờ
    ctx.strokeStyle = "#e5e7eb";
    ctx.lineWidth = 1;
    [40, 85, 130, 175].forEach((yLine) => {
      ctx.beginPath();
      ctx.moveTo(16, yLine);
      ctx.lineTo(w - 16, yLine);
      ctx.stroke();
    });

    groups.forEach((g, idx) => {
      const gx = startX + idx * stepX + stepX / 2 - barW;

      // Cột thu (đen)
      ctx.fillStyle = "#111827";
      ctx.fillRect(gx, baseY - g.income, barW, g.income);

      // Cột chi (xám)
      ctx.fillStyle = "#9ca3af";
      ctx.fillRect(gx + barW + 3, baseY - g.expense, barW, g.expense);
    });

    // Trục đáy
    ctx.strokeStyle = "#9ca3af";
    ctx.beginPath();
    ctx.moveTo(16, baseY);
    ctx.lineTo(w - 16, baseY);
    ctx.stroke();
  }

  // Xử lý modal thêm giao dịch nhanh
  const modal = document.getElementById("txModal");
  const openBtns = document.querySelectorAll("[data-open-tx]");
  const closeBtn = document.getElementById("closeTxModal");
  const txForm = document.getElementById("quickTxForm");
  const txList = document.getElementById("recentTxList");

  openBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      if (modal) modal.hidden = false;
    });
  });

  if (closeBtn && modal) {
    closeBtn.addEventListener("click", () => {
      modal.hidden = true;
    });
  }

  if (txForm && txList && modal) {
    txForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const title = document.getElementById("txTitle")?.value.trim() || "Giao dịch mới";
      const rawAmount = Number(document.getElementById("txAmount")?.value || 0);
      const type = document.getElementById("txType")?.value || "expense";
      const formatted = rawAmount.toLocaleString("vi-VN") + "đ";

      const li = document.createElement("li");
      li.className = "tx-item";
      li.innerHTML = `
        <div class="tx-left">
          <span class="tx-icon" aria-hidden="true">${type === "income" ? "+" : "•"}</span>
          <div>
            <div class="tx-name">${title}</div>
            <div class="tx-time">Vừa xong</div>
          </div>
        </div>
        <div class="tx-right">
          <div class="tx-amount ${type}">${type === "income" ? "+" : "-"}${formatted}</div>
          <div class="tx-wallet">Ví chính</div>
        </div>
      `;
      txList.prepend(li);
      txForm.reset();
      modal.hidden = true;
    });
  }
});