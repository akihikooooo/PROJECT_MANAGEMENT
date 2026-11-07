# Set up task reminders on Windows with XAMPP

This guide helps you set up browser notifications for TaskFlow on a Windows computer. When it is set up, TaskFlow can remind you **72 hours and 24 hours before an open task is due**.

Allow about 20–30 minutes. You will install one additional tool, Composer, and configure a scheduled task in Windows.

## Before you start

This guide assumes:

- XAMPP is installed at `C:\xampp` and the TaskFlow code is at `C:\xampp\htdocs\Project_Management`. If yours is elsewhere, replace these paths throughout the guide.
- You can start **Apache** and **MySQL** in the XAMPP Control Panel.
- You have an internet connection and access to a modern browser.
- XAMPP's PHP is version 8.2 or newer. To check, open PowerShell and run:

  ```powershell
  C:\xampp\php\php.exe -v
  ```

  If the version is older than 8.2, update XAMPP before continuing.

Start Apache and MySQL in the XAMPP Control Panel. In your browser, check that `http://localhost/Project_Management/` opens. If it does not, check the project folder name and make sure Apache is running.

**Keep this computer on, connected to the internet, and running XAMPP MySQL** when reminders are due. Windows Task Scheduler runs the reminder job on this computer; it cannot send reminders while the computer is asleep or off.

## 1. Install Composer and the project dependencies

Composer installs the PHP libraries that send web push notifications.

1. Download and run the Windows installer from the [official Composer download page](https://getcomposer.org/download/).
2. When the installer asks for PHP, select `C:\xampp\php\php.exe`. Leave the proxy setting empty unless your network requires a proxy.
3. Finish the installer, close any open PowerShell windows, and open a new PowerShell window.
4. Check that Composer is available:

   ```powershell
   composer --version
   ```

5. Install the libraries inside the TaskFlow project:

   ```powershell
   cd C:\xampp\htdocs\Project_Management
   composer install --no-dev
   ```

   Wait for the command to finish. It creates a `vendor` folder. If Composer reports missing PHP extensions, enable the named extensions in XAMPP's `C:\xampp\php\php.ini` and run the command again. The project requires PHP extensions including `mysqli`, `curl`, `mbstring`, and `openssl`.

## 2. Prepare the database

Open `http://localhost/phpmyadmin/` in your browser. Follow **one** of the options below.

### New TaskFlow installation

If you have not created the TaskFlow database yet:

1. In phpMyAdmin, select **Import**.
2. Choose `C:\xampp\htdocs\Project_Management\database.sql` and start the import.
3. Check that the `taskflow_db` database appears in the left sidebar. It should include tables named `tasks`, `push_subscriptions`, and `push_reminders`.

### Existing TaskFlow installation

If you already have tasks in TaskFlow, **back up your database first**: select `taskflow_db`, choose **Export**, and save the SQL file somewhere safe.

Then add the reminder tables:

1. Select `taskflow_db` in phpMyAdmin's left sidebar.
2. Choose **Import**, select `migrations\001_web_push.sql` from the TaskFlow project, and start the import.
3. Check that `push_subscriptions` and `push_reminders` appear in the database's table list.

Run this migration **only once**. It gives existing dated tasks a deadline of **23:59 UTC** on their existing due date. Tasks without a date remain undated. If the migration reports an error, do not simply rerun it: some statements may already have been applied. Check the database or restore the backup first.

The default database settings are host `127.0.0.1`, database `taskflow_db`, user `root`, and an empty password. If your installation uses different credentials, make sure the web app is already configured to connect using them; use those same credentials for the scheduled job in step 5.

## 3. Generate notification keys

Web Push uses a pair of keys, called VAPID keys, to identify your application. Open **Command Prompt** (search for `cmd` in the Start menu) and run:

```cmd
cd /d C:\xampp\htdocs\Project_Management
C:\xampp\php\php.exe -r "require 'vendor/autoload.php'; print_r(\Minishlink\WebPush\VAPID::createVapidKeys());"
```

You will see `publicKey` and `privateKey` values. Keep them available for the next steps. **Do not share the private key or commit it to the project repository.** Keep the same keys after setup; changing them means browsers must disable and re-enable notifications to subscribe again.

## 4. Configure XAMPP Apache

1. Open `C:\xampp\apache\conf\httpd.conf` in a text editor. You may need to open the editor as administrator to save changes.
2. Add the following line at the end of the file, replacing `YOUR_PUBLIC_KEY` with the `publicKey` from step 3:

   ```apache
   SetEnv TASKFLOW_VAPID_PUBLIC_KEY "YOUR_PUBLIC_KEY"
   ```

   Put the key on one line. Do not put the private key in this file.
3. Save the file. In XAMPP Control Panel, stop and start **Apache** so it reads the new setting.
4. Open `http://localhost/Project_Management/` again to confirm the app still opens. If Apache does not start, check the line for missing quotation marks and review Apache's error log from the XAMPP Control Panel.

`localhost` works for testing on this computer. To use the app from another device or a non-localhost address, configure HTTPS with a certificate trusted by that device; browser push requires a secure context.

## 5. Configure the reminder job

The browser subscription alone does not send reminders. Windows must run the TaskFlow reminder script regularly.

### Create the job file

1. Create a folder such as `C:\TaskFlow-Private` **outside** `C:\xampp\htdocs`. Restrict its Windows file permissions to your account and administrators, because it will contain your private key and possibly your database password.
2. Open Notepad and paste the following. Replace both keys and the email address. If your MySQL credentials are different from the XAMPP defaults, replace those too:

   ```bat
   @echo off
   set "TASKFLOW_DB_HOST=127.0.0.1"
   set "TASKFLOW_DB_NAME=taskflow_db"
   set "TASKFLOW_DB_USER=root"
   set "TASKFLOW_DB_PASSWORD="
   set "TASKFLOW_VAPID_PUBLIC_KEY=YOUR_PUBLIC_KEY"
   set "TASKFLOW_VAPID_PRIVATE_KEY=YOUR_PRIVATE_KEY"
   set "TASKFLOW_VAPID_SUBJECT=mailto:you@example.com"
   cd /d "C:\xampp\htdocs\Project_Management"
   "C:\xampp\php\php.exe" "send-reminders.php" >> "C:\TaskFlow-Private\reminders.log" 2>&1
   ```

   The public key must match the one in Apache. The subject should be an email address you can use for contact, preceded by `mailto:`. If a value in this batch file contains `%`, write it as `%%` so Windows passes it correctly.
3. In Notepad, choose **File → Save As**. Set **Save as type** to **All Files**, and save as `C:\TaskFlow-Private\run-reminders.bat`. Make sure the file does not end in `.txt`.
4. Double-click `run-reminders.bat` once. It may close immediately; that is normal. Open `C:\TaskFlow-Private\reminders.log`. An empty log is normal when there is nothing to send. If it reports an error, see Troubleshooting below. XAMPP may also send PHP errors to its configured PHP error log.

### Schedule it every minute

1. Open the Start menu, search for **Task Scheduler**, and open it.
2. Select **Create Task**. On the **General** tab, name it `TaskFlow reminders`. Use the Windows account that can access the private folder and project.
3. On the **Triggers** tab, select **New**. Choose a start time, check **Repeat task every: 1 minute**, and set **for a duration of: Indefinitely**. Leave the trigger enabled, then select **OK**.
4. On the **Actions** tab, select **New**. For **Start a program**, enter:

   ```text
   C:\TaskFlow-Private\run-reminders.bat
   ```

   Select **OK**.
5. On the **Conditions** tab, review the power settings. If you want reminders while a laptop is running on battery, clear **Start the task only if the computer is on AC power**. The computer still needs to be awake.
6. Save the task. Right-click **TaskFlow reminders** and select **Run** once. Check **Last Run Result** and `C:\TaskFlow-Private\reminders.log` for errors. A successful job can have an empty log when no reminder is due.

If you choose **Run whether user is logged on or not**, Windows may ask for your account password. The selected account still needs permission to read the job file and keys.

## 6. Enable notifications in your browser

1. Open `http://localhost/Project_Management/` on the same computer and sign in.
2. Open **My tasks** and select **Enable notifications**.
3. When the browser asks, choose **Allow**. The button should change to **Disable notifications**.
4. If the browser or Windows has notifications blocked, enable them in the browser's site settings and Windows notification settings.

Each browser/device needs its own subscription. Signing out removes that browser's subscription from the account; it is restored on a later signed-in visit while browser permission remains enabled. Use **Disable notifications** to unsubscribe that browser.

## 7. Test a reminder in a few minutes

The task form uses **UTC**, not your computer's local time. In PowerShell, run:

```powershell
(Get-Date).ToUniversalTime().AddHours(72).AddMinutes(5).ToString('yyyy-MM-dd HH:mm')
```

Create a test task with the displayed date and time in the TaskFlow form. This makes its 72-hour reminder due about five minutes later. Keep the browser notification permission enabled, the computer awake, MySQL running, and the scheduler enabled. Allow an extra minute or two for the job and push service.

You can also check `push_reminders` in phpMyAdmin: a row with `hours_before` equal to `72` and a `sent_at` value means the push service accepted that reminder. It does not prove that Windows displayed the notification.

The task and browser subscription must both exist **before** a reminder threshold. For example, creating a task 30 hours before its deadline will not send its already-passed 72-hour reminder; it can still send the 24-hour reminder. Completed or deleted tasks do not receive new reminders.

## Troubleshooting

- **“Notifications are not configured on the server.”** Check `TASKFLOW_VAPID_PUBLIC_KEY` in `httpd.conf`, then restart Apache. Ensure the configured key is the public key.
- **The enable button is blocked or missing.** Open the app at `http://localhost/...` on this computer, check browser and Windows notification permissions, and use a browser that supports Web Push. Other addresses need HTTPS.
- **“VAPID configuration is missing.”** Check all three `TASKFLOW_VAPID_*` values in the batch file. The private key belongs only in the protected job file.
- **“Database unavailable.”** Start MySQL in XAMPP and check the host, database name, username, and password in the job file. Make sure the web app uses the same database.
- **Composer or a library is missing.** Run `composer install --no-dev` from the project folder using XAMPP PHP 8.2 or later. The dependency list includes the HTTP client needed by Web Push.
- **No notification at the expected time.** Check that the task's deadline is UTC, the task and subscription existed before the threshold, the task is still open, and the computer and MySQL are running. In Task Scheduler, check the task is enabled and its last run result. Check `reminders.log` and XAMPP's configured PHP error log; PHP errors may not appear in the batch file's log.
- **The push service accepted the reminder but nothing appeared.** Check browser permission, Windows notification settings, and the browser's notification support. A service acceptance is not a guarantee that a notification was displayed.

A temporary delivery failure is retried while the task is eligible and before its deadline. A crash just after the push service accepts a message could cause a duplicate on retry. A notification already accepted by a push service cannot be recalled if the task is later completed or deleted.
