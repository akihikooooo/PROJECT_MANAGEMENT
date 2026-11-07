<?php
require_once "app_helpers.php";
requireAppLogin();
renderAppHeader("calendar", "Calendar", "See deadlines and keep the week in view.");
?>
<section class="surface-card calendar-surface">
	<div class="calendar-toolbar">
		<div>
			<h2 data-calendar-month>Month</h2>
			<p>Deadlines from your task list</p>
		</div>
		<div class="calendar-controls">
			<button class="icon-button" type="button" data-calendar-prev aria-label="Previous month">←</button>
			<button class="today-button" type="button" data-calendar-today>Today</button>
			<button class="icon-button" type="button" data-calendar-next aria-label="Next month">→</button>
		</div>
	</div>
	<div class="calendar-weekdays" aria-hidden="true">
		<span>Sun</span>
		<span>Mon</span>
		<span>Tue</span>
		<span>Wed</span>
		<span>Thu</span>
		<span>Fri</span>
		<span>Sat</span>
	</div>
	<div class="calendar-month-grid" data-calendar-grid></div>
</section>
<section class="surface-card calendar-agenda">
	<div class="section-heading">
		<div>
			<h2>Scheduled tasks</h2>
			<p>Upcoming deadlines from your workspace.</p>
		</div>
		<a class="text-link" href="my_task.php">Manage tasks <span aria-hidden="true">→</span></a>
	</div>
	<div class="upcoming-list" data-calendar-agenda></div>
</section>
<?php renderAppFooter(); ?>
