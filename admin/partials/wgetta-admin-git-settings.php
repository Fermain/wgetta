<?php
if (!defined('ABSPATH')) { exit; }

$site_host = parse_url(home_url('/'), PHP_URL_HOST);
if (!is_string($site_host) || $site_host === '') { $site_host = 'localhost'; }
$default_name = get_bloginfo('name');
if (!is_string($default_name) || $default_name === '') { $default_name = 'Wgetta'; }
$default_email = 'noreply@' . $site_host;

$git = get_option('wgetta_gitlab', array(
	'url' => '', 'project_id' => '', 'token' => '', 'include_meta' => 0,
	'committer_name' => $default_name, 'committer_email' => $default_email,
	'branch_template' => 'wgetta/{plan_name}', 'base_url' => home_url('/')
));
?>

<div class="wrap">
	<h1>GitLab Settings</h1>
	<div class="wgetta-card">
		<table class="form-table">
			<tr>
				<th scope="row"><label for="gitlab-url">GitLab URL</label></th>
				<td><input type="text" id="gitlab-url" class="regular-text" value="<?php echo esc_attr($git['url']); ?>" placeholder="https://gitlab.example.com" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="gitlab-project">Project ID</label></th>
				<td><input type="text" id="gitlab-project" class="regular-text" value="<?php echo esc_attr($git['project_id']); ?>" placeholder="12345" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="gitlab-token">Access Token</label></th>
				<td><input type="password" id="gitlab-token" class="regular-text" value="<?php echo esc_attr($git['token']); ?>" placeholder="glpat-..." />
					<p class="description">Token needs <code>api</code> or <code>write_repository</code> scope.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="gitlab-committer-name">Committer Name</label></th>
				<td><input type="text" id="gitlab-committer-name" class="regular-text" value="<?php echo esc_attr($git['committer_name']); ?>" placeholder="Wgetta" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="gitlab-committer-email">Committer Email</label></th>
				<td><input type="email" id="gitlab-committer-email" class="regular-text" value="<?php echo esc_attr($git['committer_email']); ?>" placeholder="noreply@<?php echo esc_attr($site_host); ?>" />
					<p class="description">Used for commit author in the temporary repository.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="gitlab-branch-template">Branch Name Template</label></th>
				<td>
					<input type="text" id="gitlab-branch-template" class="regular-text" value="<?php echo esc_attr($git['branch_template']); ?>" placeholder="wgetta/{plan_name}" />
					<p class="description">Placeholders: <code>{plan_name}</code>, <code>{job_id}</code>, <code>{date}</code> (YYYYMMDD-HHMMSS). Invalid chars are sanitized.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="gitlab-base-url">Production Base URL</label></th>
				<td>
					<input type="text" id="gitlab-base-url" class="regular-text" value="<?php echo esc_attr(isset($git['base_url']) && $git['base_url'] !== '' ? $git['base_url'] : home_url('/')); ?>" placeholder="https://site.com/" />
					<p class="description">Used for absolute URLs (canonical, OG, sitemaps). Defaults to this site's home URL.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Include metadata files</th>
				<td>
					<label><input type="checkbox" id="gitlab-include-meta" value="1" <?php checked(!empty($git['include_meta'])); ?>/> Include status.json, urls.json, command.txt, manifest.txt, plan.csv</label>
					<p class="description">Uncheck to commit only crawled files from the manifest. <code>archive.zip</code> and <code>plan.csv</code> are always excluded unless included here.</p>
				</td>
			</tr>
		</table>
		<p>
			<button class="button button-primary" id="gitlab-save">Save Settings</button>
			<button class="button" id="gitlab-test">Test Connection</button>
			<span class="spinner"></span>
		</p>
	</div>
	<?php wp_nonce_field('wgetta_ajax_nonce', 'wgetta_nonce'); ?>
</div>

<script type="text/javascript">
(function($){
	$('#gitlab-save').on('click', function(){
		var $s = $(this).siblings('.spinner'); $s.addClass('is-active');
		$.post(ajaxurl, {
			action: 'wgetta_git_save',
			nonce: $('#wgetta_nonce').val(),
			url: $('#gitlab-url').val(),
			project_id: $('#gitlab-project').val(),
			token: $('#gitlab-token').val(),
			include_meta: $('#gitlab-include-meta').is(':checked') ? 1 : 0,
			committer_name: $('#gitlab-committer-name').val(),
			committer_email: $('#gitlab-committer-email').val(),
			branch_template: $('#gitlab-branch-template').val(),
			base_url: $('#gitlab-base-url').val()
		}, function(resp){
			$s.removeClass('is-active');
			alert(resp && resp.success ? 'Saved' : (resp && resp.message ? resp.message : 'Save failed'));
		});
	});
	$('#gitlab-test').on('click', function(){
		var $s = $(this).siblings('.spinner'); $s.addClass('is-active');
		$.post(ajaxurl, {
			action: 'wgetta_git_test',
			nonce: $('#wgetta_nonce').val(),
			url: $('#gitlab-url').val(),
			project_id: $('#gitlab-project').val(),
			token: $('#gitlab-token').val()
		}, function(resp){
			$s.removeClass('is-active');
			if (resp && resp.success) {
				alert('Connected to ' + (resp.project && resp.project.path_with_namespace ? resp.project.path_with_namespace : 'project'));
			} else {
				alert((resp && resp.message) ? resp.message : 'Failed to connect');
			}
		});
	});
})(jQuery);
</script>
