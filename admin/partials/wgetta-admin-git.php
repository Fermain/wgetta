<?php
if (!defined('ABSPATH')) { exit; }

$upload_dir = wp_upload_dir();
$jobs_root = trailingslashit($upload_dir['basedir']) . 'wgetta/jobs';

// Load saved settings
$git_settings = get_option('wgetta_gitlab', array(
    'url' => '',
    'project_id' => '',
    'token' => '',
));
?>

<div class="wrap">
    <h1>GitLab Deploy</h1>

    

    <div class="wgetta-card">
        <h2>Run Deploy</h2>
        <p>Select a completed job to deploy. The entire job directory (including manifest and plan) will be committed.</p>
        <p>
            <label for="gitlab-job-filter">Filter</label>
            <input type="search" id="gitlab-job-filter" class="regular-text" placeholder="Type to filter by plan, job id, or path..." style="min-width:320px;" />
        </p>
        <table class="wp-list-table widefat fixed striped" id="gitlab-job-table">
            <thead>
                <tr>
                    <th style="width:40px;"></th>
                    <th>Plan</th>
                    <th>Files</th>
                    <th>Modified</th>
                    <th>Job</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <p>
            <button class="button" id="gitlab-dryrun" disabled>Dry Run</button>
            <button class="button button-primary" id="gitlab-deploy" disabled>Push</button>
            <span class="spinner"></span>
        </p>
        <div id="gitlab-status" class="wgetta-status"></div>
        <div class="wgetta-output-console" id="gitlab-console" style="display:none; margin-top:10px;"></div>
    </div>

    <?php wp_nonce_field('wgetta_ajax_nonce', 'wgetta_nonce'); ?>
</div>

<script type="text/javascript">
(function($){
    var JOBS = [];
    function renderJobs(filter){
        var $tbody = $('#gitlab-job-table tbody');
        $tbody.empty();
        var term = (filter || '').toLowerCase();
        var any = false;
        for (var i=0;i<JOBS.length;i++){
            var h = JOBS[i];
            if (!(h.status && h.status.indexOf('completed') === 0)) continue;
            var plan = h.plan_name || '';
            var files = (typeof h.files === 'number') ? h.files : 0;
            var when = h.modified ? (new Date(h.modified * 1000).toLocaleString()) : '';
            var job = h.id || '';
            var hay = (plan + ' ' + files + ' ' + when + ' ' + job + ' ' + (h.path || '')).toLowerCase();
            if (term && hay.indexOf(term) === -1) continue;
            var row = ''+
                '<tr data-job="'+ job +'">'+
                '<td><input type="radio" name="gitjob" value="'+ job +'"/></td>'+
                '<td>' + (plan ? ('<code>' + $('<div/>').text(plan).html() + '</code>') : '') + '</td>'+
                '<td>' + files + '</td>'+
                '<td>' + when + '</td>'+
                '<td><code>' + job + '</code></td>'+
                '<td>' + (h.zip_url ? ('<a href="' + h.zip_url + '" target="_blank">Download</a> · ') : '') + '<a href="#" class="wgetta-remove" data-job="'+ job +'">Remove</a></td>'+
                '</tr>';
            $tbody.append(row);
            any = true;
        }
        if (!any){
            $tbody.append('<tr><td colspan="6">No completed jobs found.</td></tr>');
        }
        $('#gitlab-job-table tbody tr').off('click').on('click', function(e){
            if (!$(e.target).is('input, a')){
                $(this).find('input[type=radio]').prop('checked', true).trigger('change');
            }
        });
    }
    function loadJobs(){
        $.post(ajaxurl, { action: 'wgetta_history', nonce: $('#wgetta_nonce').val() }, function(resp){
            if (resp && resp.success) {
                JOBS = resp.history || [];
                renderJobs($('#gitlab-job-filter').val());
            }
        });
    }

    // Settings moved to GitLab Settings page

    $('#gitlab-dryrun').on('click', function(){
        var job = $('input[name=gitjob]:checked').val();
        if (!job) { alert('Select a job'); return; }
        var $s = $(this).siblings('.spinner'); $s.addClass('is-active');
        $('#gitlab-console').show().text('Starting dry-run deploy for ' + job + '...');
        $.post(ajaxurl, { action: 'wgetta_git_deploy_dry', nonce: $('#wgetta_nonce').val(), job_id: job }, function(resp){
            $s.removeClass('is-active');
            if (resp && resp.success) {
                $('#gitlab-status').text('Dry run complete.');
                $('#gitlab-console').append('\n' + (resp.output || 'Would commit directory: ' + resp.job_dir));
            } else {
                $('#gitlab-status').text((resp && resp.message) ? resp.message : 'Failed');
            }
        });
    });

    $('#gitlab-deploy').on('click', function(){
        var job = $('input[name=gitjob]:checked').val();
        if (!job) { alert('Select a job'); return; }
        var $s = $(this).siblings('.spinner'); $s.addClass('is-active');
        $('#gitlab-console').show().text('Pushing job ' + job + ' to GitLab...');
        $.post(ajaxurl, { action: 'wgetta_git_deploy_push', nonce: $('#wgetta_nonce').val(), job_id: job }, function(resp){
            $s.removeClass('is-active');
            if (resp && resp.success) {
                $('#gitlab-status').text('Push complete.');
                if (resp.output) $('#gitlab-console').append('\n' + resp.output);
            } else {
                $('#gitlab-status').text((resp && resp.message) ? resp.message : 'Failed');
            }
        });
    });

    $(document).on('change', 'input[name=gitjob]', function(){
        var en = $('input[name=gitjob]:checked').length > 0;
        $('#gitlab-deploy').prop('disabled', !en);
        $('#gitlab-dryrun').prop('disabled', !en);
    });

    $('#gitlab-job-filter').on('input', function(){ renderJobs($(this).val()); });

    $(document).on('click', '.wgetta-remove', function(e){
        e.preventDefault();
        var job = $(this).data('job');
        if (!job || !confirm('Remove this job and all downloaded files?')) return;
        $.post(ajaxurl, { action: 'wgetta_job_remove', nonce: $('#wgetta_nonce').val(), job_id: job }, function(resp){
            if (resp && resp.success) {
                JOBS = JOBS.filter(function(x){ return x.id !== job; });
                renderJobs($('#gitlab-job-filter').val());
            } else {
                alert((resp && resp.message) ? resp.message : 'Failed to remove job');
            }
        });
    });

    loadJobs();
})(jQuery);
</script>

