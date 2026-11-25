<?php
declare(strict_types=1);

class CmsAndNotificationService {
    public function __construct(private PDO $db) {}

    /**
     * SR-01: Trang chủ công khai (Top 6 bài viết nổi bật & 4 thông báo mới nhất)
     */
    public function getLandingPageContent(): array {
        $articles = $this->db->query("
            SELECT article_id, title, slug, summary, thumbnail_url, views, created_at
            FROM articles
            WHERE is_published = 1
            ORDER BY views DESC, created_at DESC
            LIMIT 6
        ")->fetchAll();

        $announcements = $this->db->query("
            SELECT announcement_id, title, content, created_at
            FROM system_announcements
            WHERE is_active = 1
            ORDER BY created_at DESC
            LIMIT 4
        ")->fetchAll();

        return [
            'featured_articles'    => $articles,
            'latest_announcements' => $announcements,
        ];
    }

    public function getArticleDetail(int $articleId): array {
        $this->db->prepare("UPDATE articles SET views = views + 1 WHERE article_id = ? AND is_published = 1")
                 ->execute([$articleId]);

        $stmt = $this->db->prepare("SELECT * FROM articles WHERE article_id = ? AND is_published = 1");
        $stmt->execute([$articleId]);
        $article = $stmt->fetch();

        if (!$article) {
            Response::json(404, false, 'Không tìm thấy bài viết.');
        }
        return $article;
    }

    public function getUserNotifications(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT notification_id, title, message, type, is_read, created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function markNotificationRead(int $userId, int $notificationId): void {
        $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?")
                 ->execute([$notificationId, $userId]);
    }
}