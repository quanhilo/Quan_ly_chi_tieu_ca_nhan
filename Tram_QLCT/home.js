document.addEventListener("DOMContentLoaded", () => {
  const articleDetails = {
    "5 Cách tiết kiệm hiệu quả":
      "Ghi chép đầy đủ khoản chi nhỏ hằng ngày, trích tự động 10–20% thu nhập ngay khi nhận lương và ưu tiên nhu cầu thiết yếu.",
    "Hiểu quy tắc 50/30/20":
      "Phân bổ 50% thu nhập cho nhu cầu thiết yếu, 30% cho mong muốn cá nhân và 20% cho tiết kiệm hoặc trả nợ.",
    "Mẹo mua sắm thông minh":
      "Lập danh sách trước khi mua, chờ 24 giờ với món đồ không thiết yếu và so sánh giá giữa các nguồn.",
    "Bí quyết tích luỹ":
      "Xây dựng quỹ dự phòng tối thiểu 3–6 tháng chi phí sinh hoạt trong một ví hoặc tài khoản tách biệt.",
    "Tránh bẫy nợ tín dụng":
      "Thanh toán toàn bộ dư nợ thẻ tín dụng đúng hạn và không chi tiêu vượt quá khả năng chi trả trong tháng.",
    "Tự do tài chính tuổi 30":
      "Kết hợp kiểm soát dòng tiền chặt chẽ, đa dạng hóa nguồn thu nhập và duy trì kỷ luật đầu tư dài hạn."
  };

  const modal = document.getElementById("articleModal");
  const modalTitle = document.getElementById("articleModalTitle");
  const modalBody = document.getElementById("articleModalBody");
  const closeModal = document.getElementById("closeArticleModal");

  document.querySelectorAll(".article-card").forEach((card) => {
    card.addEventListener("click", () => {
      const title = card.dataset.title || "Bài viết";
      const countEl = card.querySelector(".view-count");
      if (countEl) {
        const current = parseFloat((countEl.textContent || "3,26").replace(",", "."));
        if (!Number.isNaN(current)) {
          countEl.textContent = `${(current + 0.01).toFixed(2).replace(".", ",")} lượt xem`;
        }
      }

      if (modal && modalTitle && modalBody) {
        modalTitle.textContent = title;
        modalBody.textContent = articleDetails[title] || "Nội dung đang được cập nhật.";
        modal.hidden = false;
      }
    });
  });

  if (closeModal && modal) {
    closeModal.addEventListener("click", () => {
      modal.hidden = true;
    });
  }
});