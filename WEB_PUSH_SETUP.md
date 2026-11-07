# Deadline reminders

Tasks have a UTC deadline and reminders at 72 and 24 hours before it. A reminder is eligible only if both the task and the device subscription existed at its threshold. If the scheduler is temporarily down, it retries eligible unsent reminders until the deadline. Completed, deleted and undated tasks are skipped. Delivery can be delayed by the scheduler or push service.

## Deployment

1. Install PHP 8.2 or later with mysqli, curl, mbstring and openssl, and Composer. Run `composer install` in this directory. The Web Push dependency is [minishlink/web-push](https://github.com/web-push-libs/web-push-php).
2. For an existing database, back it up and run `migrations/001_web_push.sql` once against the configured database. Existing dated tasks become due at **23:59 UTC** on their existing due date; undated tasks remain without a deadline. Review this default before running the migration. Fresh installations use `database.sql` instead.
3. Generate VAPID keys on the server with `php -r 'require "vendor/autoload.php"; print_r(\Minishlink\WebPush\VAPID::createVapidKeys());'`. Configure `TASKFLOW_VAPID_PUBLIC_KEY`, `TASKFLOW_VAPID_PRIVATE_KEY`, and `TASKFLOW_VAPID_SUBJECT` (for example `mailto:admin@example.com`) in both the web server and scheduler environments. Keep the private key secret and stable. Changing the keys requires devices to subscribe again.
4. Serve the app over HTTPS and configure a cron job to run `php /absolute/path/to/send-reminders.php` every minute. Supply the database environment variables from `db.php` to the job as well. For example, using a protected wrapper that exports the configuration:

v   * * * * * /absolute/path/to/taskflow-reminders-wrapper
   ```

   Check the cron and PHP error logs for failures. The scheduler uses a MySQL named lock to prevent overlapping runs; all instances must connect to the same database server. Its database account needs read/write access to the reminder and subscription tables.
5. Sign in, open My tasks, and enable notifications on each browser/device. Browser notification permission and browser-specific Web Push support are required. Signing out removes that browser's subscription from the account; it is restored on the next authenticated visit if browser permission remains enabled.

Push endpoints are restricted to known Google, Mozilla, Apple, and Windows push domains; update the allowlist in `api.php` if a supported browser uses a different service. Do not log subscription endpoints or VAPID secrets.

A successful push-service response is recorded after sending. If the process crashes between acceptance and recording, a retry may duplicate a notification; Web Push cannot guarantee exactly-once delivery. A notification already accepted by a push service cannot be recalled if a task is subsequently completed or deleted.
