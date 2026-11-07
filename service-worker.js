self.addEventListener("push", (event) => {
  let payload = {};
  try { payload = event.data?.json() || {}; } catch (_) {}
  event.waitUntil(self.registration.showNotification("Task deadline reminder", {
    body: payload.body || "A task is approaching its deadline.",
    data: { url: new URL("my_task.php", self.registration.scope).href },
  }));
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  event.waitUntil(clients.openWindow(event.notification.data.url));
});
