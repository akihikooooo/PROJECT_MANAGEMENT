ALTER TABLE tasks ADD COLUMN due_at DATETIME NULL AFTER due_date;
ALTER TABLE tasks ADD KEY idx_tasks_reminder (is_completed, due_at);
UPDATE tasks SET due_at = TIMESTAMP(due_date, '23:59:00') WHERE due_date IS NOT NULL;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    endpoint VARCHAR(2048) NOT NULL,
    endpoint_hash CHAR(64) NOT NULL UNIQUE,
    p256dh VARCHAR(255) NOT NULL,
    auth_key VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_push_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_reminders (
    task_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NOT NULL,
    deadline DATETIME NOT NULL,
    hours_before TINYINT UNSIGNED NOT NULL,
    sent_at DATETIME NULL,
    PRIMARY KEY (task_id, subscription_id, deadline, hours_before),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES push_subscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

