<?php
require_once "app_helpers.php";
requireAppLogin();
renderAppHeader("my_task", "My tasks", "Capture the next action and keep your priorities moving.");
?>
<section class="surface-card form-surface">
	<div class="section-heading">
		<div>
			<h2>Add a task</h2>
			<p>Tasks are saved to your workspace database.</p>
		</div>
	</div>
	<form class="entry-form task-entry" data-task-form>
		<label class="field field-wide"><span>Task name</span><input name="title" type="text"
				placeholder="What needs to get done?" maxlength="100" required></label>
		<label class="field"><span>Project</span><select name="project_id" data-project-select>
				<option value="">No project</option>
			</select></label>
		<label class="field"><span>Due date</span><input name="due" type="date" required></label>
		<label class="field"><span>Priority</span><select name="priority">
				<option>Medium</option>
				<option>High</option>
				<option>Low</option>
			</select></label>
		<button class="primary-button" type="submit">＋ Add task</button>
	</form>
</section>
<section class="surface-card list-surface">
	<div class="section-heading list-heading">
		<div>
			<h2>Task list</h2>
			<p><span data-task-count>0</span> tasks in your workspace.</p>
		</div>
		<div class="filter-tabs" role="group" aria-label="Filter tasks"><button type="button" class="filter-tab active"
				data-filter="all">All</button><button type="button" class="filter-tab"
				data-filter="open">Open</button><button type="button" class="filter-tab"
				data-filter="done">Completed</button></div>
	</div>
	<div class="task-list full-task-list" data-task-list></div>
</section>
<?php renderAppFooter(); ?>