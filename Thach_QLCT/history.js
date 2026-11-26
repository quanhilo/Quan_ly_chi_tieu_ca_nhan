document.addEventListener("DOMContentLoaded", () => {
  const greetingEl = document.getElementById("greetingUser");
  try {
    const loggedUser = JSON.parse(localStorage.getItem("tram_logged_in_user") || "null");
    if (loggedUser && loggedUser.fullname && greetingEl) {
      greetingEl.textContent = `Xin chào, ${loggedUser.fullname}!`;
    }
  } catch (_) {}

  const searchInput = document.getElementById("searchInput");
  const categoryFilter = document.getElementById("categoryFilter");
  const walletFilter = document.getElementById("walletFilter");
  const typeFilter = document.getElementById("typeFilter");
  const minAmount = document.getElementById("minAmount");
  const maxAmount = document.getElementById("maxAmount");
  const toggleAdvancedBtn = document.getElementById("toggleAdvancedBtn");
  const advancedPanel = document.getElementById("advancedPanel");
  const resetFilterBtn = document.getElementById("resetFilterBtn");

  function applyFilters() {
    const keyword = (searchInput?.value || "").trim().toLowerCase();
    const catVal = categoryFilter?.value || "all";
    const walletVal = walletFilter?.value || "all";
    const typeVal = typeFilter?.value || "all";
    const minVal = Number(minAmount?.value || 0);
    const maxVal = Number(maxAmount?.value || 0);

    document.querySelectorAll(".tx-row").forEach((row) => {
      const cat = row.dataset.category || "";
      const wallet = row.dataset.wallet || "";
      const type = row.dataset.type || "";
      const amount = Number(row.dataset.amount || 0);
      const text = row.textContent.toLowerCase();

      const matchKeyword = !keyword || text.includes(keyword);
      const matchCat = catVal === "all" || cat === catVal;
      const matchWallet = walletVal === "all" || wallet === walletVal;
      const matchType = typeVal === "all" || type === typeVal;
      const matchMin = !minVal || amount >= minVal;
      const matchMax = !maxVal || amount <= maxVal;

      row.hidden = !(matchKeyword && matchCat && matchWallet && matchType && matchMin && matchMax);
    });
  }

  [searchInput, categoryFilter, walletFilter, typeFilter, minAmount, maxAmount].forEach((el) => {
    if (el) el.addEventListener("input", applyFilters);
    if (el) el.addEventListener("change", applyFilters);
  });

  if (toggleAdvancedBtn && advancedPanel) {
    toggleAdvancedBtn.addEventListener("click", () => {
      advancedPanel.hidden = !advancedPanel.hidden;
    });
  }

  if (resetFilterBtn) {
    resetFilterBtn.addEventListener("click", () => {
      if (searchInput) searchInput.value = "";
      if (categoryFilter) categoryFilter.value = "all";
      if (walletFilter) walletFilter.value = "all";
      if (typeFilter) typeFilter.value = "all";
      if (minAmount) minAmount.value = "";
      if (maxAmount) maxAmount.value = "";
      applyFilters();
    });
  }

  // Modal thêm giao dịch
  const modal = document.getElementById("addTxModal");
  const openBtn = document.getElementById("openAddTxBtn");
  const closeBtn = document.getElementById("closeModalBtn");
  const addForm = document.getElementById("addTxForm");

  if (openBtn && modal) {
    openBtn.addEventListener("click", () => {
      modal.hidden = false;
    });
  }

  if (closeBtn && modal) {
    closeBtn.addEventListener("click", () => {
      modal.hidden = true;
    });
  }

  if (addForm && modal) {
    addForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const cat = document.getElementById("newCategory")?.value.trim() || "Ăn uống";
      const wallet = document.getElementById("newWallet")?.value || "Ví VCB";
      const amount = Number(document.getElementById("newAmount")?.value || 0);
      const type = document.getElementById("newType")?.value || "expense";
      const formattedAmount = amount.toLocaleString("vi-VN") + "đ";

      const firstGroup = document.querySelector(".date-group");
      if (firstGroup) {
        const row = document.createElement("div");
        row.className = "tx-row";
        row.dataset.category = cat;
        row.dataset.wallet = wallet;
        row.dataset.type = type;
        row.dataset.amount = String(amount);
        row.innerHTML = `
          <div class="tx-left">
            <span class="row-plus">+</span>
            <div class="cat-badge">
              <span class="cat-icon">${type === "income" ? "💵" : "🧾"}</span>
              <span class="cat-name">${cat}</span>
            </div>
          </div>
          <div class="tx-time">Vừa xong</div>
          <div class="tx-wallet">${wallet}</div>
          <div class="tx-amount ${type}">${type === "income" ? "+" : "-"}${formattedAmount}</div>
          <button type="button" class="more-btn" aria-label="Tùy chọn giao dịch">•••</button>
        `;
        firstGroup.appendChild(row);
      }

      addForm.reset();
      modal.hidden = true;
    });
  }
});