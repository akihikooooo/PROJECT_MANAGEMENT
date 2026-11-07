(function () {
  "use strict";

  const state = { tasks: [], projects: [], members: [], filter: "all" };
  const feedback = document.querySelector("[data-feedback]");
  const csrfToken =
    document.querySelector('meta[name="csrf-token"]')?.content || "";

  function escapeHtml(value) {
    return String(value ?? "").replace(
      /[&<>"']/g,
      (character) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[character],
    );
  }

  function dateString(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
  }

  function formatDate(value, options) {
    if (!value) return "No date";
    const date = new Date(`${value}T12:00:00`);
    if (Number.isNaN(date.getTime())) return "No date";
    return new Intl.DateTimeFormat(
      "en",
      options || { month: "short", day: "numeric" },
    ).format(date);
  }

  function dayDifference(value) {
    if (!value) return null;
    const target = new Date(`${value}T00:00:00`);
    const current = new Date();
    current.setHours(0, 0, 0, 0);
    if (Number.isNaN(target.getTime())) return null;
    return Math.round((target - current) / 86400000);
  }

  async function requestApi(method, body) {
    const options = { method, headers: { Accept: "application/json" } };
    if (body) {
      options.headers["Content-Type"] = "application/json";
      options.headers["X-CSRF-Token"] = csrfToken;
      options.body = JSON.stringify(body);
    }
    const response = await fetch("api.php", options);
    let result;
    try {
      result = await response.json();
    } catch (error) {
      throw new Error("The server returned an invalid response.");
    }
    if (!response.ok) throw new Error(result.error || "The request failed.");
    return result;
  }

  function showFeedback(message, isError) {
    if (!feedback) return;
    feedback.textContent = message;
    feedback.classList.toggle("is-error", Boolean(isError));
    feedback.hidden = !message;
  }

  function completed(task) {
    return task.completed === true || Number(task.completed) === 1;
  }

  function priorityClass(priority) {
    return ["High", "Medium", "Low"].includes(priority)
      ? priority.toLowerCase()
      : "medium";
  }

  function taskMarkup(task, allowDelete) {
    const difference = dayDifference(task.due);
    const dueText = !task.due
      ? "No date"
      : difference < 0
        ? `${Math.abs(difference)}d overdue`
        : difference === 0
          ? "Today"
          : formatDate(task.due);
    const isDone = completed(task);
    const dateClass =
      difference !== null && difference < 0 && !isDone
        ? "task-date overdue"
        : "task-date";
    return `<div class="task-row">
      <button class="task-check${isDone ? " is-done" : ""}" type="button" data-task-toggle="${escapeHtml(task.id)}" aria-label="${isDone ? "Mark as open" : "Mark as complete"}" aria-pressed="${isDone ? "true" : "false"}">✓</button>
      <div class="task-main"><div class="task-title${isDone ? " is-done" : ""}">${escapeHtml(task.title)}</div><div class="task-meta">${escapeHtml(task.project || "General")}</div></div>
      <div class="task-trailing"><span class="priority-tag ${priorityClass(task.priority)}">${escapeHtml(task.priority)}</span><span class="${dateClass}">${escapeHtml(dueText)}${task.due_time ? ` · ${escapeHtml(task.due_time)} UTC` : ""}</span>${allowDelete ? `<button class="delete-task" type="button" data-task-delete="${escapeHtml(task.id)}" aria-label="Delete ${escapeHtml(task.title)}" title="Delete task">×</button>` : ""}</div>
    </div>`;
  }

  function upcomingMarkup(task) {
    const date = task.due ? new Date(`${task.due}T12:00:00`) : null;
    const day = date
      ? new Intl.DateTimeFormat("en", { day: "2-digit" }).format(date)
      : "--";
    const month = date
      ? new Intl.DateTimeFormat("en", { month: "short" }).format(date)
      : "TBD";
    return `<div class="upcoming-item"><div class="date-block"><strong>${day}</strong><span>${month}</span></div><div><div class="upcoming-name">${escapeHtml(task.title)}</div><div class="upcoming-project">${escapeHtml(task.project || "General")}</div></div><span class="priority-tag ${priorityClass(task.priority)}">${escapeHtml(task.priority)}</span></div>`;
  }

  function renderDashboard() {
    const openTasks = state.tasks.filter((task) => !completed(task));
    const stats = {
      open: openTasks.length,
      today: openTasks.filter((task) => dayDifference(task.due) === 0).length,
      projects: state.projects.length,
      members: state.members.length,
    };
    document.querySelectorAll("[data-stat]").forEach((node) => {
      node.textContent = stats[node.dataset.stat] ?? 0;
    });

    const taskTarget = document.querySelector("[data-dashboard-tasks]");
    if (taskTarget) {
      const priorityOrder = { High: 0, Medium: 1, Low: 2 };
      const selected = [...openTasks]
        .sort(
          (a, b) =>
            priorityOrder[a.priority] - priorityOrder[b.priority] ||
            (a.due || "").localeCompare(b.due || ""),
        )
        .slice(0, 4);
      taskTarget.innerHTML = selected.length
        ? selected.map((task) => taskMarkup(task, false)).join("")
        : '<div class="empty-state">No open tasks yet.</div>';
    }

    const projectTarget = document.querySelector("[data-dashboard-projects]");
    if (projectTarget) {
      projectTarget.innerHTML = state.projects.length
        ? state.projects
            .slice(0, 4)
            .map((project) => {
              const related = state.tasks.filter(
                (task) => String(task.project_id) === String(project.id),
              );
              const progress = related.length
                ? Math.round(
                    (related.filter(completed).length * 100) / related.length,
                  )
                : Number(project.progress) || 0;
              return `<div class="project-progress-row"><div class="project-progress-heading"><span>${escapeHtml(project.name)}</span><span>${progress}%</span></div><div class="progress-track"><div class="progress-fill ${escapeHtml(project.tone || "green")}" style="width:${Math.max(0, Math.min(100, progress))}%"></div></div></div>`;
            })
            .join("")
        : '<div class="empty-state">No projects yet.</div>';
    }

    const upcomingTarget = document.querySelector("[data-upcoming-tasks]");
    if (upcomingTarget) {
      const selected = openTasks
        .filter((task) => dayDifference(task.due) !== null)
        .sort((a, b) => a.due.localeCompare(b.due))
        .slice(0, 5);
      upcomingTarget.innerHTML = selected.length
        ? selected.map(upcomingMarkup).join("")
        : '<div class="empty-state">No upcoming deadlines.</div>';
    }
  }

  function renderTaskPage() {
    const target = document.querySelector("[data-task-list]");
    if (!target) return;
    const visible = state.tasks.filter(
      (task) =>
        state.filter === "all" ||
        (state.filter === "open" && !completed(task)) ||
        (state.filter === "done" && completed(task)),
    );
    target.innerHTML = visible.length
      ? visible.map((task) => taskMarkup(task, true)).join("")
      : '<div class="empty-state">No tasks in this view.</div>';
    const count = document.querySelector("[data-task-count]");
    if (count) count.textContent = state.tasks.length;
  }

  function renderProjectOptions() {
    const select = document.querySelector("[data-project-select]");
    if (!select) return;
    const selected = select.value;
    select.innerHTML =
      '<option value="">No project</option>' +
      state.projects
        .map(
          (project) =>
            `<option value="${escapeHtml(project.id)}">${escapeHtml(project.name)}</option>`,
        )
        .join("");
    if (state.projects.some((project) => String(project.id) === selected))
      select.value = selected;
  }

  function renderProjects() {
    const target = document.querySelector("[data-project-grid]");
    if (!target) return;
    target.innerHTML = state.projects.length
      ? state.projects
          .map((project) => {
            const related = state.tasks.filter(
              (task) => String(task.project_id) === String(project.id),
            );
            const progress = Math.max(
              0,
              Math.min(
                100,
                related.length
                  ? Math.round(
                      (related.filter(completed).length * 100) / related.length,
                    )
                  : Number(project.progress) || 0,
              ),
            );
            const tone = ["green", "coral", "blue"].includes(project.tone)
              ? project.tone
              : "green";
            const openCount = related.filter((task) => !completed(task)).length;
            return `<article class="project-card"><div class="project-card-top"><div><h2>${escapeHtml(project.name)}</h2><p>${escapeHtml(project.owner || "Unassigned")}</p></div><span class="project-swatch ${tone}"></span></div><div class="progress-track"><div class="progress-fill ${tone === "green" ? "" : tone}" style="width:${progress}%"></div></div><div class="project-card-footer"><span>${progress}% complete</span><span>${project.due ? `Due ${escapeHtml(formatDate(project.due))}` : "No target date"}</span></div><div class="project-card-footer"><span class="status-tag">Active</span><span>${openCount} open tasks</span></div></article>`;
          })
          .join("")
      : '<div class="empty-state">No projects yet. Create one above.</div>';
    renderProjectOptions();
  }

  function renderTeam() {
    const target = document.querySelector("[data-team-grid]");
    if (!target) return;
    const tones = ["", "coral", "yellow"];
    target.innerHTML = state.members.length
      ? state.members
          .map(
            (member, index) =>
              `<article class="team-card"><div class="member-avatar ${tones[index % tones.length]}">${escapeHtml((member.name || "?").trim().charAt(0).toUpperCase())}</div><div class="member-info"><h2>${escapeHtml(member.name)}</h2><p>${escapeHtml(member.email)}</p><span class="member-role">${escapeHtml(member.role || "Team member")}</span></div></article>`,
          )
          .join("")
      : '<div class="empty-state">No teammates yet. Add someone above.</div>';
  }

  let calendarCursor = new Date(
    new Date().getFullYear(),
    new Date().getMonth(),
    1,
  );

  function renderCalendar() {
    const target = document.querySelector("[data-calendar-grid]");
    if (!target) return;
    const monthLabel = document.querySelector("[data-calendar-month]");
    if (monthLabel)
      monthLabel.textContent = new Intl.DateTimeFormat("en", {
        month: "long",
        year: "numeric",
      }).format(calendarCursor);

    const year = calendarCursor.getFullYear();
    const month = calendarCursor.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPreviousMonth = new Date(year, month, 0).getDate();
    const cells = [];

    for (let index = 0; index < 42; index += 1) {
      let day;
      let cellDate;
      let outside = false;
      if (index < firstDay) {
        day = daysInPreviousMonth - firstDay + index + 1;
        cellDate = new Date(year, month - 1, day);
        outside = true;
      } else if (index >= firstDay + daysInMonth) {
        day = index - firstDay - daysInMonth + 1;
        cellDate = new Date(year, month + 1, day);
        outside = true;
      } else {
        day = index - firstDay + 1;
        cellDate = new Date(year, month, day);
      }
      const dateValue = dateString(cellDate);
      const isToday = dateValue === dateString(new Date());
      const events = state.tasks.filter((task) => task.due === dateValue);
      cells.push(
        `<div class="calendar-day${outside ? " outside-month" : ""}${isToday ? " today" : ""}"><span class="calendar-day-number">${day}</span>${events
          .slice(0, 2)
          .map(
            (task) =>
              `<div class="calendar-event${completed(task) ? " done" : task.priority === "High" ? " high" : ""}" title="${escapeHtml(task.title)}">${escapeHtml(task.title)}</div>`,
          )
          .join(
            "",
          )}${events.length > 2 ? `<div class="calendar-event">+${events.length - 2} more</div>` : ""}</div>`,
      );
    }
    target.innerHTML = cells.join("");

    const agenda = document.querySelector("[data-calendar-agenda]");
    if (agenda) {
      const monthTasks = state.tasks
        .filter(
          (task) =>
            task.due &&
            task.due.slice(0, 7) ===
              `${year}-${String(month + 1).padStart(2, "0")}`,
        )
        .sort((a, b) => a.due.localeCompare(b.due));
      agenda.innerHTML = monthTasks.length
        ? monthTasks.map(upcomingMarkup).join("")
        : '<div class="empty-state">No tasks scheduled this month.</div>';
    }
  }

  function renderAll() {
    renderDashboard();
    renderTaskPage();
    renderProjects();
    renderTeam();
    renderCalendar();
  }

  async function reloadData() {
    const data = await requestApi("GET");
    state.tasks = data.tasks || [];
    state.projects = data.projects || [];
    state.members = data.members || [];
    renderAll();
  }

  async function performAction(body) {
    await requestApi("POST", body);
    await reloadData();
  }

  document.addEventListener("click", async function (event) {
    const toggle = event.target.closest("[data-task-toggle]");
    if (toggle) {
      const task = state.tasks.find(
        (item) => String(item.id) === toggle.dataset.taskToggle,
      );
      if (!task) return;
      try {
        await performAction({
          action: "toggle_task",
          id: task.id,
          completed: !completed(task),
        });
        showFeedback("Task status saved.", false);
      } catch (error) {
        showFeedback(error.message, true);
      }
      return;
    }

    const remove = event.target.closest("[data-task-delete]");
    if (remove) {
      try {
        await performAction({
          action: "delete_task",
          id: remove.dataset.taskDelete,
        });
        showFeedback("Task deleted.", false);
      } catch (error) {
        showFeedback(error.message, true);
      }
      return;
    }

    const filter = event.target.closest("[data-filter]");
    if (filter) {
      state.filter = filter.dataset.filter;
      document
        .querySelectorAll("[data-filter]")
        .forEach((button) =>
          button.classList.toggle("active", button === filter),
        );
      renderTaskPage();
    }
  });

  document
    .querySelector("[data-task-form]")
    ?.addEventListener("submit", async function (event) {
      event.preventDefault();
      const form = event.currentTarget;
      const values = new FormData(form);
      try {
        await performAction({
          action: "create_task",
          title: String(values.get("title")).trim(),
          project_id: values.get("project_id") || null,
          due: values.get("due") || null,
          due_time: values.get("due_time"),
          priority: values.get("priority"),
        });
        form.reset();
        form.querySelector('[name="due"]').value = dateString(new Date());
        showFeedback("Task added.", false);
      } catch (error) {
        showFeedback(error.message, true);
      }
    });

  document
    .querySelector("[data-project-form]")
    ?.addEventListener("submit", async function (event) {
      event.preventDefault();
      const form = event.currentTarget;
      const values = new FormData(form);
      const tones = ["green", "coral", "blue"];
      try {
        await performAction({
          action: "create_project",
          name: String(values.get("name")).trim(),
          owner: String(values.get("owner")).trim(),
          due: values.get("due") || null,
          tone: tones[state.projects.length % tones.length],
        });
        form.reset();
        showFeedback("Project created.", false);
      } catch (error) {
        showFeedback(error.message, true);
      }
    });

  document
    .querySelector("[data-member-form]")
    ?.addEventListener("submit", async function (event) {
      event.preventDefault();
      const form = event.currentTarget;
      const values = new FormData(form);
      try {
        await performAction({
          action: "create_member",
          name: String(values.get("name")).trim(),
          email: String(values.get("email")).trim(),
          role: String(values.get("role")).trim(),
        });
        form.reset();
        showFeedback("Teammate added.", false);
      } catch (error) {
        showFeedback(error.message, true);
      }
    });

  document
    .querySelector("[data-calendar-prev]")
    ?.addEventListener("click", function () {
      calendarCursor = new Date(
        calendarCursor.getFullYear(),
        calendarCursor.getMonth() - 1,
        1,
      );
      renderCalendar();
    });
  document
    .querySelector("[data-calendar-next]")
    ?.addEventListener("click", function () {
      calendarCursor = new Date(
        calendarCursor.getFullYear(),
        calendarCursor.getMonth() + 1,
        1,
      );
      renderCalendar();
    });
  document
    .querySelector("[data-calendar-today]")
    ?.addEventListener("click", function () {
      calendarCursor = new Date(
        new Date().getFullYear(),
        new Date().getMonth(),
        1,
      );
      renderCalendar();
    });

  const menuToggle = document.querySelector("[data-menu-toggle]");
  const menuClose = document.querySelector("[data-menu-close]");
  function setMenuOpen(open) {
    document.body.classList.toggle("menu-open", open);
    menuToggle?.setAttribute("aria-expanded", String(open));
  }
  menuToggle?.addEventListener("click", function () {
    setMenuOpen(!document.body.classList.contains("menu-open"));
  });
  menuClose?.addEventListener("click", function () {
    setMenuOpen(false);
  });

  const dueInput = document.querySelector('[data-task-form] [name="due"]');
  if (dueInput) dueInput.value = dateString(new Date());
  const pushButton = document.querySelector("[data-push-toggle]");
  const pushStatus = document.querySelector("[data-push-status]");
  async function setupPush() {
    if (!("serviceWorker" in navigator) || !("PushManager" in window) || !("Notification" in window)) {
      if (pushStatus) pushStatus.textContent = "Notifications are not supported by this browser.";
      return;
    }
    const data = await requestApi("GET");
    if (!data.push_public_key) {
      if (pushStatus) pushStatus.textContent = "Notifications are not configured on the server.";
      return;
    }
    const registration = await navigator.serviceWorker.register("service-worker.js");
    let subscription = await registration.pushManager.getSubscription();
    if (subscription) await requestApi("POST", { action: "save_push_subscription", subscription: subscription.toJSON() });
    if (!pushButton) return;
    function updateButton() {
      pushButton.disabled = Notification.permission === "denied";
      pushButton.textContent = subscription ? "Disable notifications" : "Enable notifications";
      pushStatus.textContent = Notification.permission === "denied" ? "Notifications are blocked in your browser settings." : "";
    }
    updateButton();
    pushButton.addEventListener("click", async () => {
      pushButton.disabled = true;
      try {
        if (subscription) {
          await requestApi("POST", { action: "remove_push_subscription", endpoint: subscription.endpoint });
          await subscription.unsubscribe();
          subscription = null;
        } else {
          if (await Notification.requestPermission() !== "granted") throw new Error("Notification permission was not granted.");
          const key = data.push_public_key.replace(/-/g, "+").replace(/_/g, "/");
          const bytes = Uint8Array.from(atob(key.padEnd(Math.ceil(key.length / 4) * 4, "=")), (c) => c.charCodeAt(0));
          subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: bytes });
          try {
            await requestApi("POST", { action: "save_push_subscription", subscription: subscription.toJSON() });
          } catch (error) {
            await subscription.unsubscribe();
            subscription = null;
            throw error;
          }
        }
        updateButton();
      } catch (error) {
        updateButton();
        pushStatus.textContent = error.message;
      }
    });
  }
  setupPush().catch((error) => { if (pushStatus) pushStatus.textContent = error.message; });
  renderAll();
  reloadData().catch((error) => showFeedback(error.message, true));
})();
