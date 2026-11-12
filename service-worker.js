self.addEventListener("push", (event) => {
  let payload = {};
  try { payload = event.data?.json() || {}; } catch (_) {}
  const deadline = new Date(payload.deadline);
  const body = payload.title && !Number.isNaN(deadline.getTime())
    ? `${payload.title} ${payload.reached ? "reached its deadline" : "is due"} at ${deadline.toLocaleString()}.`
    : payload.body || "A task is approaching its deadline.";
  event.waitUntil(self.registration.showNotification("Task deadline reminder", {
    body,
    data: { url: new URL("my_task.php", self.registration.scope).href },
  }));
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  event.waitUntil(clients.openWindow(event.notification.data.url));
});
