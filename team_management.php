<?php
require_once "app_helpers.php";
requireAppLogin();
renderAppHeader("team_management", "Team", "Keep the people behind the work in one place.");
?>
<section class="surface-card form-surface">
	<div class="section-heading"><div><h2>Add a teammate</h2><p>Team members are saved to your workspace database.</p></div></div>
	<form class="entry-form team-entry" data-member-form>
		<label class="field"><span>Full name</span><input name="name" type="text" placeholder="e.g. Jordan Lee" maxlength="60" required></label>
		<label class="field"><span>Email address</span><input name="email" type="email" placeholder="name@example.com" maxlength="100" required></label>
		<label class="field"><span>Role</span><input name="role" type="text" placeholder="e.g. Designer" maxlength="50"></label>
		<button class="primary-button" type="submit">＋ Add teammate</button>
	</form>
</section>
<section class="team-grid" data-team-grid></section>
<?php renderAppFooter(); ?>
