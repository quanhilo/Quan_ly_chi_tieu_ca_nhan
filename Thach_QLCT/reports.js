document.addEventListener("DOMContentLoaded", () => {
  const datasets = {
    month: {
      range: "01/11/2026 - 12/11/2026",
      incomeText: "+35.200.000 đ",
      expenseText: "-18.450.000 đ",
      reserveText: "= +16.750.00 đ",
      reserveSub: "Tích luỹ tốt",
      // Tỷ lệ theo thang tối đa 15.000.000 đ (chiều cao vùng cột 210px)
      buckets: [
        { label: "01-07/11", incomePct: 84, expensePct: 55 },
        { label: "08-14/11", incomePct: 78, expensePct: 50 },
        { label: "15-21/11", incomePct: 94, expensePct: 57 },
        { label: "22-28/11", incomePct: 84, expensePct: 33 },
        { label: "29-30/11", incomePct: 86, expensePct: 48 }
      ]
    },
    week: {
      range: "05/11/2026 - 12/11/2026",
      incomeText: "+9.800.000 đ",
      expenseText: "-4.650.000 đ",
      reserveText: "= +5.150.000 đ",
      reserveSub: "Tích luỹ tốt",
      buckets: [
        { label: "01-07/11", incomePct: 62, expensePct: 38 },
        { label: "08-14/11", incomePct: 70, expensePct: 42 },
        { label: "15-21/11", incomePct: 58, expensePct: 30 },
        { label: "22-28/11", incomePct: 65, expensePct: 28 },
        { label: "29-30/11", incomePct: 52, expensePct: 25 }
      ]
    },
    "prev-month": {
      range: "01/10/2026 - 31/10/2026",
      incomeText: "+31.400.000 đ",
      expenseText: "-19.900.000 đ",
      reserveText: "= +11.500.000 đ",
      reserveSub: "Ổn định",
      buckets: [
        { label: "01-07/10", incomePct: 75, expensePct: 52 },
        { label: "08-14/10", incomePct: 80, expensePct: 60 },
        { label: "15-21/10", incomePct: 88, expensePct: 54 },
        { label: "22-28/10", incomePct: 72, expensePct: 46 },
        { label: "29-31/10", incomePct: 68, expensePct: 40 }
      ]
    },
    "next-year": {
      range: "01/01/2027 - 31/12/2027",
      incomeText: "+420.000.000 đ",
      expenseText: "-240.000.000 đ",
      reserveText: "= +180.000.000 đ",
      reserveSub: "Kế hoạch dự kiến",
      buckets: [
        { label: "Quý 1", incomePct: 82, expensePct: 48 },
        { label: "Quý 2", incomePct: 86, expensePct: 52 },
        { label: "Quý 3", incomePct: 90, expensePct: 55 },
        { label: "Quý 4", incomePct: 95, expensePct: 58 },
        { label: "Dự phòng", incomePct: 70, expensePct: 30 }
      ]
    }
  };

  const barsContainer = document.getElementById("barsContainer");
  const periodBtns = document.querySelectorAll(".period-pill");
  const dateRangeText = document.getElementById("dateRangeText");
  const totalIncomeVal = document.getElementById("totalIncomeVal");
  const totalExpenseVal = document.getElementById("totalExpenseVal");
  const reserveBalanceVal = document.getElementById("reserveBalanceVal");
  const reserveSub = document.querySelector(".kpi-sub");
  const exportBtn = document.getElementById("exportReportBtn");
  const customDateBox = document.getElementById("customDateBox");

  function renderReport(periodKey) {
    const data = datasets[periodKey] || datasets.month;
    if (dateRangeText) dateRangeText.textContent = data.range;
    if (totalIncomeVal) totalIncomeVal.textContent = data.incomeText;
    if (totalExpenseVal) totalExpenseVal.textContent = data.expenseText;
    if (reserveBalanceVal) reserveBalanceVal.textContent = data.reserveText;
    if (reserveSub) reserveSub.textContent = data.reserveSub;

    if (!barsContainer) return;
    barsContainer.innerHTML = data.buckets
      .map(
        (b) => `
      <div class="bar-group">
        <div class="bar-pair">
          <div class="bar bar-income" style="height: ${b.incomePct}%;" title="Tổng thu: ${b.label}"></div>
          <div class="bar bar-expense" style="height: ${b.expensePct}%;" title="Tổng chi: ${b.label}"></div>
        </div>
        <div class="bar-x-label">${b.label}</div>
      </div>
    `
      )
      .join("");
  }

  periodBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      periodBtns.forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      renderReport(btn.dataset.period || "month");
    });
  });

  if (customDateBox && dateRangeText) {
    customDateBox.addEventListener("click", () => {
      const nextRange = window.prompt(
        "Nhập khoảng ngày (ví dụ: 01/11/2026 - 26/11/2026):",
        dateRangeText.textContent || "01/11/2026 - 12/11/2026"
      );
      if (nextRange && nextRange.trim()) {
        dateRangeText.textContent = nextRange.trim();
      }
    });
  }

  if (exportBtn) {
    exportBtn.addEventListener("click", () => {
      const range = dateRangeText?.textContent || "01/11/2026 - 12/11/2026";
      const csvContent =
        "Khoang thoi gian,Tong thu,Tong chi,So du du phong\n" +
        `"${range}","${totalIncomeVal?.textContent || ""}","${totalExpenseVal?.textContent || ""}","${reserveBalanceVal?.textContent || ""}"`;
      const blob = new Blob(["\uFEFF" + csvContent], { type: "text/csv;charset=utf-8;" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = "bao-cao-tong-quan-thu-chi.csv";
      a.click();
      URL.revokeObjectURL(url);
    });
  }

  renderReport("month");
});