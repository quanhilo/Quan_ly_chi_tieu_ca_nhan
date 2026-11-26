document.addEventListener("DOMContentLoaded", () => {
  const greetingEl = document.getElementById("greetingUser");
  try {
    const loggedUser = JSON.parse(localStorage.getItem("tram_logged_in_user") || "null");
    if (loggedUser && loggedUser.fullname && greetingEl) {
      greetingEl.textContent = `Xin chào, ${loggedUser.fullname}!`;
    }
  } catch (_) {}

  let budgets = [
    {
      id: 1,
      category: "Ăn uống",
      spent: 2000000,
      limit: 5000000,
      icon: "",
      emptyIcon: true
    },
    {
      id: 2,
      category: "Di chuyển",
      spent: 1600000,
      limit: 2000000,
      icon: "🚗",
      emptyIcon: false
    },
    {
      id: 3,
      category: "Mua sắm",
      spent: 4950000,
      limit: 5000000,
      icon: "🛒",
      emptyIcon: false
    },
    {
      id: 4,
      category: "Giải trí",
      spent: 1200000,
      limit: 1000000,
      icon: "🎬",
      emptyIcon: false
    }
  ];

  const budgetList = document.getElementById("budgetList");
  const cycleSelect = document.getElementById("cycleSelect");
  const cycleDisplay = document.getElementById("cycleDisplay");
  const statusFilter = document.getElementById("statusFilter");

  const modal = document.getElementById("budgetModal");
  const modalTitle = document.getElementById("budgetModalTitle");
  const openAddBtn = document.getElementById("openAddBudgetBtn");
  const closeModalBtn = document.getElementById("closeBudgetModal");
  const budgetForm = document.getElementById("budgetForm");
  const editBudgetId = document.getElementById("editBudgetId");
  const budgetCategoryInput = document.getElementById("budgetCategoryInput");
  const budgetSpentInput = document.getElementById("budgetSpentInput");
  const budgetLimitInput = document.getElementById("budgetLimitInput");

  function formatVND(num) {
    return Number(num || 0).toLocaleString("vi-VN") + " đ";
  }

  function getStatusInfo(item) {
    const pct = item.limit > 0 ? Math.round((item.spent / item.limit) * 100) : 0;
    if (item.spent > item.limit) {
      return {
        key: "exceeded",
        label: `Đã vượt ${formatVND(item.spent - item.limit)}`,
        pct,
        barWidth: 100,
        remainText: `Còn lại\n0 đ`
      };
    }
    if (pct >= 90) {
      return {
        key: "near-limit",
        label: "Sắp vượt mức",
        pct,
        barWidth: pct,
        remainText: `Còn lại\n${formatVND(item.limit - item.spent)}`
      };
    }
    if (pct >= 75) {
      return {
        key: "warning",
        label: "Cảnh báo",
        pct,
        barWidth: pct,
        remainText: `Còn lại\n${formatVND(item.limit - item.spent)}`
      };
    }
    return {
      key: "safe",
      label: "An toàn",
      pct,
      barWidth: pct,
      remainText: `Còn lại\n${formatVND(item.limit - item.spent)}`
    };
  }

  function renderBudgets() {
    if (!budgetList) return;
    const filterVal = statusFilter?.value || "all";

    const items = budgets.filter((item) => {
      if (filterVal === "all") return true;
      return getStatusInfo(item).key === filterVal;
    });

    budgetList.innerHTML = items
      .map((item) => {
        const info = getStatusInfo(item);
        return `
        <article class="budget-card" data-id="${item.id}">
          <div class="budget-icon-box ${item.emptyIcon ? "empty-icon" : ""}" aria-hidden="true">
            ${item.icon}
          </div>

          <div class="budget-body">
            <div class="budget-top-line">
              <span class="budget-title">${item.category}</span>
              <span class="status-badge ${info.key}">${info.label}</span>
            </div>

            <div class="budget-sub-line">
              <span class="spent-text">Đã chi ${formatVND(item.spent)} / ${formatVND(item.limit)}</span>
              <span class="percent-text">${info.pct}%</span>
            </div>

            <div class="progress-track">
              <div class="progress-fill ${info.key}" style="width: ${info.barWidth}%;"></div>
            </div>
          </div>

          <div class="budget-right">
            <div class="card-tools">
              <button type="button" class="tool-btn" data-edit="${item.id}" title="Chỉnh sửa">✒</button>
              <button type="button" class="tool-btn" data-delete="${item.id}" title="Xóa">🗑</button>
            </div>
            <div class="remain-text">${info.remainText.replace("\n", "<br />")}</div>
          </div>
        </article>
      `;
      })
      .join("");
  }

  if (cycleSelect && cycleDisplay) {
    cycleSelect.addEventListener("change", () => {
      cycleDisplay.textContent = `Chu kì: ${cycleSelect.value}`;
    });
  }

  if (statusFilter) {
    statusFilter.addEventListener("change", renderBudgets);
  }

  if (openAddBtn && modal) {
    openAddBtn.addEventListener("click", () => {
      if (modalTitle) modalTitle.textContent = "Thêm ngân sách";
      if (editBudgetId) editBudgetId.value = "";
      if (budgetForm) budgetForm.reset();
      modal.hidden = false;
    });
  }

  if (closeModalBtn && modal) {
    closeModalBtn.addEventListener("click", () => {
      modal.hidden = true;
    });
  }

  if (budgetList) {
    budgetList.addEventListener("click", (e) => {
      const target = e.target;
      if (!(target instanceof HTMLElement)) return;
      const btn = target.closest("button");
      if (!btn) return;

      const editId = btn.dataset.edit;
      const deleteId = btn.dataset.delete;

      if (editId) {
        const found = budgets.find((b) => String(b.id) === editId);
        if (found && modal) {
          if (modalTitle) modalTitle.textContent = "Chỉnh sửa ngân sách";
          if (editBudgetId) editBudgetId.value = String(found.id);
          if (budgetCategoryInput) budgetCategoryInput.value = found.category;
          if (budgetSpentInput) budgetSpentInput.value = String(found.spent);
          if (budgetLimitInput) budgetLimitInput.value = String(found.limit);
          modal.hidden = false;
        }
      }

      if (deleteId) {
        if (window.confirm("Bạn có chắc muốn xóa ngân sách này không?")) {
          budgets = budgets.filter((b) => String(b.id) !== deleteId);
          renderBudgets();
        }
      }
    });
  }

  if (budgetForm && modal) {
    budgetForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const category = budgetCategoryInput?.value.trim() || "Danh mục mới";
      const spent = Number(budgetSpentInput?.value || 0);
      const limit = Number(budgetLimitInput?.value || 1);
      const idVal = editBudgetId?.value;

      if (idVal) {
        budgets = budgets.map((b) =>
          String(b.id) === idVal ? { ...b, category, spent, limit } : b
        );
      } else {
        budgets.push({
          id: Date.now(),
          category,
          spent,
          limit,
          icon: "💼",
          emptyIcon: false
        });
      }

      modal.hidden = true;
      renderBudgets();
    });
  }

  renderBudgets();
});