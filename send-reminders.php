<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db.php';
if (!$conn) {
    fwrite(STDERR, "Database unavailable.\n");
    exit(1);
}
if (!is_file(__DIR__ . '/vendor/autoload.php')) {
    fwrite(STDERR, "Install Composer dependencies before running reminders.\n");
    exit(1);
}
require_once __DIR__ . '/vendor/autoload.php';

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

$publicKey = getenv('TASKFLOW_VAPID_PUBLIC_KEY');
$privateKey = getenv('TASKFLOW_VAPID_PRIVATE_KEY');
$subject = getenv('TASKFLOW_VAPID_SUBJECT');
if (!$publicKey || !$privateKey || !$subject) {
    fwrite(STDERR, "VAPID configuration is missing.\n");
    exit(1);
}

// A connection-scoped lock prevents concurrent schedulers from sending the same reminder.
$lock = $conn->query("SELECT GET_LOCK('taskflow_push_reminders', 0) AS acquired")->fetch_assoc();
if ((int) $lock['acquired'] !== 1) exit(0);

$webPush = new WebPush(['VAPID' => [
    'subject' => $subject, 'publicKey' => $publicKey, 'privateKey' => $privateKey,
]]);

foreach ([72, 24] as $hours) {
    $sql = "SELECT t.id AS task_id, t.title, t.due_at, s.id AS subscription_id,
                   s.endpoint, s.p256dh, s.auth_key
            FROM tasks t
            JOIN users u ON u.id = t.user_id AND u.status = 'active'
            JOIN push_subscriptions s ON s.user_id = t.user_id
            LEFT JOIN push_reminders r ON r.task_id = t.id AND r.subscription_id = s.id
                AND r.deadline = t.due_at AND r.hours_before = ?
            WHERE t.is_completed = 0 AND t.due_at > UTC_TIMESTAMP()
                AND DATE_SUB(t.due_at, INTERVAL ? HOUR) <= UTC_TIMESTAMP()
                AND t.created_at <= DATE_SUB(t.due_at, INTERVAL ? HOUR)
                AND s.created_at <= DATE_SUB(t.due_at, INTERVAL ? HOUR)
                AND r.sent_at IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $hours, $hours, $hours);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as $row) {
        // Recheck immediately before delivery so changed or completed tasks are skipped.
        $check = $conn->prepare("SELECT 1 FROM tasks t JOIN users u ON u.id = t.user_id JOIN push_subscriptions s ON s.user_id = t.user_id WHERE t.id = ? AND s.id = ? AND t.due_at = ? AND t.is_completed = 0 AND t.due_at > UTC_TIMESTAMP() AND u.status = 'active'");
        $check->bind_param('iis', $row['task_id'], $row['subscription_id'], $row['due_at']);
        $check->execute();
        $eligible = $check->get_result()->fetch_row();
        $check->close();
        if (!$eligible) continue;
        try {
            $subscription = Subscription::create(['endpoint' => $row['endpoint'], 'keys' => [
                'p256dh' => $row['p256dh'], 'auth' => $row['auth_key'],
            ]]);
            $ttl = max(0, strtotime($row['due_at'] . ' UTC') - time());
            $report = $webPush->sendOneNotification($subscription, json_encode([
                'body' => $row['title'] . ' is due at ' . substr($row['due_at'], 0, 16) . ' UTC.',
            ], JSON_THROW_ON_ERROR), ['TTL' => $ttl]);
            if ($report->isSuccess()) {
                $save = $conn->prepare("INSERT INTO push_reminders (task_id, subscription_id, deadline, hours_before, sent_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE sent_at = VALUES(sent_at)");
                $save->bind_param('iisi', $row['task_id'], $row['subscription_id'], $row['due_at'], $hours);
                $save->execute();
                $save->close();
            } elseif ($report->isSubscriptionExpired()) {
                $delete = $conn->prepare('DELETE FROM push_subscriptions WHERE id = ?');
                $delete->bind_param('i', $row['subscription_id']);
                $delete->execute();
                $delete->close();
            } else {
                error_log('TaskFlow push delivery failed for subscription ' . $row['subscription_id']);
            }
        } catch (Throwable $exception) {
            error_log('TaskFlow push delivery failed for subscription ' . $row['subscription_id'] . ': ' . get_class($exception));
        }
    }
}
$conn->query("SELECT RELEASE_LOCK('taskflow_push_reminders')");
