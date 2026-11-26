document.addEventListener("DOMContentLoaded", () => {
  const defaultCategories = [
    { id: 1, name: "Ăn uống", type: "expense", color: "#f97316", icon: "☕" },
    { id: 2, name: "Di chuyển", type: "expense", color: "#22c55e", icon: "▱" },
    { id: 3, name: "Nhà cửa & Hóa đơn", type: "expense", color: "#1d9bf0", icon: "⌂" },
    { id: 4, name: "Mua sắm", type: "expense", color: "#f43f5e", icon: "▣" },
    { id: 5, name: "Giải trí", type: "expense", color: "#9333ea", icon: "▣" },
    { id: 6, name: "Y tế & Sức khỏe", type: "expense", color: "#0d9488", icon: "+" },
    { id: 7, name: "Giáo dục", type: "expense", color: "#eab308", icon: "▤" },
    { id: 8, name: "Quà tặng & Đóng góp", type: "expense", color: "#ec4899", icon: "♣" },
    { id: 9, name: "Chi phí khác", type: "expense", color: "#9ca3af", icon: "⚙" },
    { id: 10, name: "Lương & Thưởng", type: "income", color: "#16a34a", icon: "💵" }
  ];

  let categories = [...defaultCategories];
  let activeTab = "expense";

  const catGrid = document.getElementById("catGrid");
  const searchInput = document.getElementById("catSearchInput");
  const tabBtns = document.querySelectorAll(".tab-btn");
  const groupTitle = document.getElementById("groupTitle");
  const groupSub = document.getElementById("groupSub");

  const modal = document.getElementById("catModal");
  const modalTitle = document.getElementById("catModalTitle");
  const openAddBtn = document.getElementById("openAddCatBtn");
  const closeModalBtn = document.getElementById("closeCatModal");
  const catForm = document.getElementById("catForm");
  const editCatId = document.getElementById("editCatId");
  const catNameInput = document.getElementById("catNameInput");
  const catTypeSelect = document.getElementById("catTypeSelect");
  const catColorSelect = document.getElementById("catColorSelect");

  function pad2(n) {
    return String(n).padStart(2, "0");
  }

  function updateCounters() {
    const expCount = categories.filter((c) => c.type === "expense").length;
    const incCount = categories.filter((c) => c.type === "income").length;
    const totalEl = document.getElementById("totalCount");
    const expEl = document.getElementById("expenseCount");
    const incEl = document.getElementById("incomeCount");

    if (totalEl) totalEl.textContent = `${pad2(expCount + incCount)} danh mục`;
    if (expEl) expEl.textContent = pad2(expCount);
    if (incEl) incEl.textContent = pad2(incCount);
  }

  function renderCategories() {
    if (!catGrid) return;
    const keyword = (searchInput?.value || "").trim().toLowerCase();

    const filtered = categories.filter((item) => {
      const matchTab = item.type === activeTab;
      const matchText = !keyword || item.name.toLowerCase().includes(keyword);
      return matchTab && matchText;
    });

    if (groupTitle && groupSub) {
      if (activeTab === "expense") {
        groupTitle.textContent = "Danh mục chi tiêu";
        groupSub.textContent = "Các nhóm chi tiêu thường dùng";
      } else {
        groupTitle.textContent = "Danh mục thu nhập";
        groupSub.textContent = "Các nguồn thu nhập thường dùng";
      }
    }

    catGrid.innerHTML = filtered
      .map(
        (cat, idx) => `
      <article class="cat-card ${idx < 2 && activeTab === "expense" ? "selected" : ""}" data-id="${cat.id}">
        <div class="cat-actions">
          <button type="button" class="card-action-btn" data-edit="${cat.id}" title="Chỉnh sửa">✎</button>
          <button type="button" class="card-action-btn" data-delete="${cat.id}" title="Xóa">☒</button>
        </div>
        <div class="cat-circle-icon" style="background-color: ${cat.color};">${cat.icon}</div>
        <div class="cat-info">
          <h3>${cat.name}</h3>
          <span>${cat.type === "expense" ? "Danh mục chi" : "Danh mục thu"}</span>
        </div>
      </article>
    `
      )
      .join("");

    updateCounters();
  }

  tabBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      tabBtns.forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      activeTab = btn.dataset.tab || "expense";
      renderCategories();
    });
  });

  if (searchInput) {
    searchInput.addEventListener("input", renderCategories);
  }

  if (openAddBtn && modal) {
    openAddBtn.addEventListener("click", () => {
      if (modalTitle) modalTitle.textContent = "Thêm danh mục mới";
      if (editCatId) editCatId.value = "";
      if (catForm) catForm.reset();
      if (catTypeSelect) catTypeSelect.value = activeTab;
      modal.hidden = false;
    });
  }

  if (closeModalBtn && modal) {
    closeModalBtn.addEventListener("click", () => {
      modal.hidden = true;
    });
  }

  if (catGrid) {
    catGrid.addEventListener("click", (e) => {
      const target = e.target;
      if (!(target instanceof HTMLElement)) return;

      const editId = target.dataset.edit;
      const deleteId = target.dataset.delete;

      if (editId) {
        const found = categories.find((c) => String(c.id) === editId);
        if (found && modal) {
          if (modalTitle) modalTitle.textContent = "Chỉnh sửa danh mục";
          if (editCatId) editCatId.value = String(found.id);
          if (catNameInput) catNameInput.value = found.name;
          if (catTypeSelect) catTypeSelect.value = found.type;
          if (catColorSelect) catColorSelect.value = found.color;
          modal.hidden = false;
        }
      }

      if (deleteId) {
        if (window.confirm("Bạn có chắc muốn xóa danh mục này không?")) {
          categories = categories.filter((c) => String(c.id) !== deleteId);
          renderCategories();
        }
      }
    });
  }

  if (catForm && modal) {
    catForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const name = catNameInput?.value.trim() || "Danh mục mới";
      const type = catTypeSelect?.value || "expense";
      const color = catColorSelect?.value || "#f97316";
      const idVal = editCatId?.value;

      if (idVal) {
        categories = categories.map((c) =>
          String(c.id) === idVal ? { ...c, name, type, color } : c
        );
      } else {
        categories.push({
          id: Date.now(),
          name,
          type,
          color,
          icon: "•"
        });
      }

      modal.hidden = true;
      renderCategories();
    });
  }

  renderCategories();
});