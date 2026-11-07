<?php
require_once "app_helpers.php";
requireAppLogin();
renderAppHeader("dashboard", "Overview", "A clear view of what is moving and what needs your attention.");
?>
<section class="metric-grid" aria-label="Workspace summary">
	<article class="metric-card"><span class="metric-label">Open tasks</span><strong data-stat="open">0</strong><span
			class="metric-foot"><i class="metric-mark mint"></i> Work in progress</span></article>
	<article class="metric-card"><span class="metric-label">Due today</span><strong data-stat="today">0</strong><span
			class="metric-foot"><i class="metric-mark coral"></i> Keep an eye on these</span></article>
	<article class="metric-card"><span class="metric-label">Projects</span><strong data-stat="projects">0</strong><span
			class="metric-foot"><i class="metric-mark blue"></i> Active initiatives</span></article>
	<article class="metric-card"><span class="metric-label">Team members</span><strong
			data-stat="members">0</strong><span class="metric-foot"><i class="metric-mark yellow"></i> In your
			workspace</span></article>
</section>

<section class="dashboard-columns">
	<article class="surface-card task-overview">
		<div class="section-heading">
			<div>
				<h2>Priority tasks</h2>
				<p>Your next steps, all in one place.</p>
			</div><a class="text-link" href="my_task.php">View all <span aria-hidden="true">→</span></a>
		</div>
		<div class="task-list" data-dashboard-tasks></div>
	</article>
	<article class="surface-card project-overview">
		<div class="section-heading">
			<div>
				<h2>Projects</h2>
				<p>Progress across active work.</p>
			</div><a class="text-link" href="project.php">All projects <span aria-hidden="true">→</span></a>
		</div>
		<div class="project-list" data-dashboard-projects></div>
	</article>
</section>

<section class="surface-card upcoming-card">
	<div class="section-heading">
		<div>
			<h2>Coming up</h2>
			<p>Tasks scheduled soon.</p>
		</div><a class="text-link" href="calendar.php">Open calendar <span aria-hidden="true">→</span></a>
	</div>
	<div class="upcoming-list" data-upcoming-tasks></div>
</section>
<?php renderAppFooter(); ?>
