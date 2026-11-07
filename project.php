<?php
require_once "app_helpers.php";
requireAppLogin();
renderAppHeader("project", "Projects", "Organize your work into clear, trackable initiatives.");
?>
<section class="surface-card form-surface">
	<div class="section-heading">
		<div>
			<h2>Create a project</h2>
			<p>Give a piece of work a name and a target date.</p>
		</div>
	</div>
	<form class="entry-form project-entry" data-project-form>
		<label class="field field-wide"><span>Project name</span><input name="name" type="text"
				placeholder="e.g. Website refresh" maxlength="70" required></label>
		<label class="field"><span>Owner or client</span><input name="owner" type="text" placeholder="e.g. Marketing"
				maxlength="50"></label>
		<label class="field"><span>Target date</span><input name="due" type="date"></label>
		<button class="primary-button" type="submit">＋ Create project</button>
	</form>
</section>
<section class="project-card-grid" data-project-grid></section>
<?php renderAppFooter(); ?>