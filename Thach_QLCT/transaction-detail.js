document.addEventListener("DOMContentLoaded", () => {
  const ownerTitle = document.getElementById("ownerTitle");
  try {
    const loggedUser = JSON.parse(localStorage.getItem("tram_logged_in_user") || "null");
    if (loggedUser && loggedUser.fullname && ownerTitle) {
      ownerTitle.textContent = `Chi tiết giao dịch của ${loggedUser.fullname}`;
    }
  } catch (_) {}

  const editBtn = document.getElementById("editTxBtn");
  const deleteBtn = document.getElementById("deleteTxBtn");
  const modal = document.getElementById("editModal");
  const closeModal = document.getElementById("closeEditModal");
  const editForm = document.getElementById("editTxForm");

  if (editBtn && modal) {
    editBtn.addEventListener("click", () => {
      modal.hidden = false;
    });
  }

  if (closeModal && modal) {
    closeModal.addEventListener("click", () => {
      modal.hidden = true;
    });
  }

  if (editForm && modal) {
    editForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const amountNum = Number(document.getElementById("editAmountInput")?.value || 45000);
      const noteText = document.getElementById("editNoteInput")?.value.trim() || "";
      const formatted = amountNum.toLocaleString("vi-VN") + " đ";

      const summaryAmount = document.getElementById("summaryAmount");
      const metaAmount = document.getElementById("metaAmount");
      const receiptAmount = document.getElementById("receiptAmount");
      const metaNote = document.getElementById("metaNote");

      if (summaryAmount) summaryAmount.textContent = `-${formatted}`;
      if (metaAmount) metaAmount.textContent = formatted;
      if (receiptAmount) receiptAmount.textContent = formatted;
      if (metaNote && noteText) metaNote.textContent = noteText;

      modal.hidden = true;
    });
  }

  if (deleteBtn) {
    deleteBtn.addEventListener("click", () => {
      if (window.confirm("Bạn có chắc muốn xoá giao dịch #TXN-102948 không?")) {
        window.location.href = "history.html";
      }
    });
  }
});