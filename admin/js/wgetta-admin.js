/**
 * Wgetta Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Initialize based on current page
        var currentPage = getParameterByName('page');
        
        if (currentPage === 'wgetta-plan-copy') {
            initPlanCopyPage();
        } else if (currentPage === 'wgetta-plan-run') {
            initPlanRunPage();
        }
        
    });
    
    /**
     * Settings Page Functions
     */
    // legacy settings page removed
    
    /**
     * Plan Page Functions
     */
    // legacy plan page removed
    
    /**
     * Copy Page Functions
     */
    // legacy copy page removed
    
    /**
     * Helper Functions
     */
    
    function displayDryRunResults(urls) {
        $('#dry-run-results').show();
        $('#url-count').text(urls.length);
        
        var html = '';
        for (var i = 0; i < urls.length; i++) {
            html += '<div class="url-item">' + escapeHtml(urls[i]) + '</div>';
        }
        
        $('#dry-run-url-list').html(html || '<p>No URLs found</p>');
    }
    
    function testRegexPatterns(urls, patterns) {
        var included = [];
        var excluded = [];
        
        for (var i = 0; i < urls.length; i++) {
            var url = urls[i];
            var isExcluded = false;
            
            for (var j = 0; j < patterns.length; j++) {
                try {
                    var regex = new RegExp(patterns[j]);
                    if (regex.test(url)) {
                        isExcluded = true;
                        break;
                    }
                } catch (e) {
                    console.error('Invalid regex pattern:', patterns[j]);
                }
            }
            
            if (isExcluded) {
                excluded.push(url);
            } else {
                included.push(url);
            }
        }
        
        // Display results
        displayRegexResults(included, excluded);
    }
    
    function displayRegexResults(included, excluded) {
        $('#regex-results').show();
        
        // Update counts
        $('.included-count').text(included.length);
        $('.excluded-count').text(excluded.length);
        
        // Display included URLs
        var includedHtml = '';
        for (var i = 0; i < included.length; i++) {
            includedHtml += '<div class="url-item">' + escapeHtml(included[i]) + '</div>';
        }
        $('#included-urls').html(includedHtml || '<p>No URLs included</p>');
        
        // Display excluded URLs
        var excludedHtml = '';
        for (var i = 0; i < excluded.length; i++) {
            excludedHtml += '<div class="url-item">' + escapeHtml(excluded[i]) + '</div>';
        }
        $('#excluded-urls').html(excludedHtml || '<p>No URLs excluded</p>');
        
        // Show summary
        $('#plan-summary').show();
        $('#summary-total').text(included.length + excluded.length);
        $('#summary-included').text(included.length);
        $('#summary-excluded').text(excluded.length);

        // Compose and display final command (with current exclusions if any)
        var baseCmd = $('#wgetta-base-cmd').val() || '';
        var patterns = [];
        $('.wgetta-regex-input').each(function() {
            var v = $(this).val().trim();
            if (v) patterns.push(v);
        });
        var finalCmd = baseCmd;
        if (patterns.length > 0) {
            var combined = '(' + patterns.join(')|(') + ')';
            finalCmd += " --reject-regex='" + combined + "'";
        }
        $('#final-command').text(finalCmd);
    }
    
    function executeWgetCommand() {
        var data = {
            action: 'wgetta_execute',
            nonce: $('#wgetta_nonce').val(),
            directory: $('#download-directory').val(),
            create_log: $('#create-log').is(':checked') ? 1 : 0,
            background: $('#background-mode').is(':checked') ? 1 : 0
        };
        
        $.post(wgetta_ajax.ajax_url, data, function(response) {
            if (response.success) {
                showExecutionResults(response.data);
            } else {
                alert('Execution failed: ' + (response.message || 'Unknown error'));
            }
        });
    }

    /**
     * Plan Copy Page Functions
     */
    function initPlanCopyPage() {
        var currentJobId = null;
        var currentPlan = [];
        
        $('#generate-plan').on('click', function() {
            var $btn = $(this), $spin = $btn.siblings('.spinner'), $status = $('#plan-status');
            $btn.prop('disabled', true); $spin.addClass('is-active');
            // Save command before generating
            var cmd = $('#wget-command').val();
            $.post(wgetta_ajax.ajax_url, { action: 'wgetta_save_settings', nonce: wgetta_ajax.nonce, wgetta_cmd: cmd }, function(){
                $.post(wgetta_ajax.ajax_url, {
                    action: 'wgetta_plan_generate',
                    nonce: $('#wgetta_nonce').val()
                }, function(resp) {
                $spin.removeClass('is-active'); $btn.prop('disabled', false);
                if (resp && resp.success) {
                    currentJobId = resp.job_id;
                    currentPlan = resp.urls || [];
                    buildTreeAndList(currentPlan);
                    try { document.getElementById('step-review').open = true; } catch(e){}
                } else {
                    $status.text(resp && resp.message ? resp.message : 'Failed to generate plan');
                }
                });
            });
        });

        $('#save-plan').on('click', function() {
            var urls = collectPlan();
            currentPlan = urls;
            $.post(wgetta_ajax.ajax_url, {
                action: 'wgetta_plan_save',
                nonce: $('#wgetta_nonce').val(),
                job_id: currentJobId,
                urls: urls
            }, function(resp) {
                showNotice(resp && resp.success ? 'success' : 'error', resp && resp.message ? resp.message : 'Save failed');
            });
        });

        $('#save-plan-named').on('click', function() {
            var urls = collectPlan();
            var name = $('#plan-name').val().trim();
            if (!name) { showNotice('error', 'Please provide a plan name'); return; }
            $.post(wgetta_ajax.ajax_url, {
                action: 'wgetta_plan_save_named',
                nonce: $('#wgetta_nonce').val(),
                name: name,
                urls: urls
            }, function(resp) {
                if (resp && resp.success) {
                    var runUrl = (typeof wgetta_ajax !== 'undefined' && wgetta_ajax.ajax_url)
                        ? wgetta_ajax.ajax_url.replace('admin-ajax.php', 'admin.php?page=wgetta-plan-run')
                        : 'admin.php?page=wgetta-plan-run';
                    window.location.href = runUrl;
                } else {
                    showNotice('error', (resp && resp.message) ? resp.message : 'Save failed');
                }
            });
        });

        $('#load-plan-select').on('change input', function() {
            var name = $('#load-plan-select').val();
            if (!name) { $('#delete-plan').hide(); return; }
            // Create a new job based on this named plan and load its URLs
            $.post(wgetta_ajax.ajax_url, { action: 'wgetta_plan_create', nonce: $('#wgetta_nonce').val(), name: name }, function(createResp){
                if (createResp && createResp.success) {
                    currentJobId = createResp.job_id;
                    // Now load URLs from the named plan for editing
                    $.post(wgetta_ajax.ajax_url, {
                        action: 'wgetta_plan_load',
                        nonce: $('#wgetta_nonce').val(),
                        name: name
                    }, function(resp) {
                        if (resp && resp.success) {
                            currentPlan = resp.urls || [];
                            buildTreeAndList(currentPlan);
                            try { document.getElementById('step-review').open = true; } catch(e){}
                            $('#delete-plan').show().data('plan', name);
                        }
                    });
                } else {
                    showNotice('error', (createResp && createResp.message) ? createResp.message : 'Failed to prepare plan');
                }
            });
        });

        $('#delete-plan').on('click', function(){
            var name = $(this).data('plan');
            if (!name) { return; }
            if (!confirm('Delete the plan "' + name + '"? This cannot be undone.')) { return; }
            var $btn = $(this), $spin = $('#step-command .spinner');
            $btn.prop('disabled', true); $spin.addClass('is-active');
            $.post(wgetta_ajax.ajax_url, {
                action: 'wgetta_plan_delete',
                nonce: $('#wgetta_nonce').val(),
                name: name
            }, function(resp){
                $spin.removeClass('is-active'); $btn.prop('disabled', false);
                if (resp && resp.success) {
                    showNotice('success', 'Plan deleted');
                    $('#delete-plan').hide().data('plan', '');
                    // Reload list and clear tree
                    loadNamedPlans();
                    buildTreeAndList([]);
                } else {
                    showNotice('error', (resp && resp.message) ? resp.message : 'Failed to delete plan');
                }
            });
        });

        // Execute Plan flow removed; use the Run Plan view

        function buildTreeAndList(urls) {
            // Build FancyTree data: one node per host, children per path segment as folders, with leaves per URL
            var hosts = {};
            var skipSet = {};
            var added = {};
            urls.forEach(function(u){
                try {
                    var raw = u;
                    var isSkip = /\s+#SKIP$/.test(raw);
                    if (isSkip) {
                        raw = raw.replace(/\s+#SKIP$/, '');
                        skipSet[raw] = true;
                    }
                    var a = document.createElement('a'); a.href = raw;
                    // canonical key: normalize root path to '/'
                    var path = a.pathname || '/';
                    if (path !== '/' ) { path = path.replace(/\/+$/,''); }
                    var canon = (a.protocol || 'http:') + '//' + (a.host || '') + path + (a.search || '');
                    if (added[canon]) { return; }
                    var host = a.host || 'root';
                    var parts = (a.pathname || '/').split('/').filter(Boolean);
                    hosts[host] = hosts[host] || { title: host, folder: true, expanded: true, children: [] };
                    var node = hosts[host];
                    var branch = node;
                    var pathAcc = '';
                    for (var i2 = 0; i2 < parts.length; i2++) {
                        pathAcc += '/' + parts[i2];
                        var found = (branch.children || []).find(function(c){ return c.key === pathAcc; });
                        if (!found) {
                            found = { title: parts[i2], key: pathAcc, folder: i2 < parts.length - 1, children: [] };
                            branch.children.push(found);
                        }
                        branch = found;
                    }
                    // attach leaf node representing the URL
                    var leafTitle = a.pathname.replace(/\/$/, '') || a.pathname;
                    branch.children.push({ title: leafTitle, checkbox: true, data: { url: canon } });
                    added[canon] = true;
                } catch(e){}
            });
            var source = Object.keys(hosts).map(function(h){ return hosts[h]; });
            var $treeEl = $('#plan-tree');
            var isInit = $treeEl.hasClass('ui-fancytree') || !!$treeEl.data('ui-fancytree') || !!$treeEl.data('fancytree');
            if (isInit) {
                try { $treeEl.fancytree('destroy'); } catch (e) { /* ignore */ }
            }
            $treeEl.empty();
            $treeEl.fancytree({
                checkbox: true,
                selectMode: 3,
                source: source,
                extensions: ['filter'],
                filter: { autoApply: true, counter: true, fuzzy: false, mode: 'hide' }
            });
            // Select leaves according to skipSet (default: selected)
            var tree = $treeEl.fancytree('getTree');
            tree.visit(function(n){
                if (n.data && n.data.url) {
                    var sel = !skipSet[n.data.url];
                    n.setSelected(sel);
                }
            });

            // Filter input
            $('#plan-tree-filter').off('input').on('input', function(){
                var tree = $treeEl.fancytree('getTree');
                tree.filterNodes($(this).val());
            });

            // Expand/Collapse
            $('#tree-expand-all').off('click').on('click', function(){
                $treeEl.fancytree('getTree').expandAll(true);
            });
            $('#tree-collapse-all').off('click').on('click', function(){
                $treeEl.fancytree('getTree').expandAll(false);
            });
        }

        // No include/exclude buttons: selection directly determines which URLs are taken

        function loadNamedPlans() {
            $.post(wgetta_ajax.ajax_url, { action: 'wgetta_plan_list', nonce: $('#wgetta_nonce').val() }, function(resp){
                if (resp && resp.success) {
                    var opts = '<option value="">Load named plan…</option>';
                    for (var i = 0; i < resp.plans.length; i++) {
                        var p = resp.plans[i];
                        var label = escapeHtml(p.name) + ' (' + (p.included || 0) + '/' + (p.total || 0) + ')';
                        opts += '<option value="' + escapeHtml(p.name) + '">' + label + '</option>';
                    }
                    $('#load-plan-select').html(opts);
                }
            });
        }

        function collectPlan() {
            // Plan tracks all URLs. Selection determines which are active.
            var tree = $('#plan-tree').fancytree('getTree');
            var urls = [];
            if (!tree) return urls;
            tree.visit(function(n){
                if (n.data && n.data.url) {
                    urls.push(n.data.url + (n.selected ? '' : ' #SKIP'));
                }
            });
            return urls;
        }

        // Initial load of named plans
        loadNamedPlans();
    }

    /**
     * Run Plan Page (minimal UI)
     */
    function initPlanRunPage() {
        loadRunPlans();

        $('#run-plan-execute').on('click', function(){
            var name = $('#run-plan-select').val();
            if (!name) { showNotice('error', 'Please select a plan'); return; }
            // Create a job from the named plan
            $.post(wgetta_ajax.ajax_url, { action: 'wgetta_plan_create', nonce: $('#wgetta_nonce').val(), name: name }, function(createResp){
                if (createResp && createResp.success) {
                    var jobId = createResp.job_id;
                    $('#run-plan-status').text('Plan queued (Job ' + jobId + ')');
                    $('#run-plan-progress').show();
                    $('#run-plan-progress-status').text('Queued');
                    $('#run-plan-console').text('');
                    // Attach metadata (plan name)
                    $.post(wgetta_ajax.ajax_url, { action: 'wgetta_set_job_meta', nonce: $('#wgetta_nonce').val(), job_id: jobId, meta: { plan_name: name } });
                    // Queue execution
                    $.post(wgetta_ajax.ajax_url, { action: 'wgetta_plan_execute', nonce: $('#wgetta_nonce').val(), job_id: jobId }, function(execResp){
                        if (execResp && execResp.success) {
                            startRunPlanPolling(jobId);
                        } else {
                            showNotice('error', (execResp && execResp.message) ? execResp.message : 'Failed to start plan');
                        }
                    });
                } else {
                    showNotice('error', (createResp && createResp.message) ? createResp.message : 'Failed to prepare plan');
                }
            });
        });

        function loadRunPlans() {
            $.post(wgetta_ajax.ajax_url, { action: 'wgetta_plan_list', nonce: $('#wgetta_nonce').val() }, function(resp){
                if (resp && resp.success) {
                    var opts = '<option value="">Select a plan…</option>';
                    for (var i = 0; i < resp.plans.length; i++) {
                        var p = resp.plans[i];
                        var label = escapeHtml(p.name) + ' (' + (p.included || 0) + '/' + (p.total || 0) + ')';
                        opts += '<option value="' + escapeHtml(p.name) + '">' + label + '</option>';
                    }
                    $('#run-plan-select').html(opts);
                }
            });
        }

        function startRunPlanPolling(jobId) {
            var offset = 0;
            var interval = setInterval(function(){
                $.post(wgetta_ajax.ajax_url, { action: 'wgetta_log_tail', nonce: $('#wgetta_nonce').val(), job_id: jobId, offset: offset }, function(resp){
                    if (resp && resp.success) {
                        if (resp.content) {
                            var $c = $('#run-plan-console');
                            $c.append(escapeHtml(resp.content));
                            $c.scrollTop($c[0].scrollHeight);
                        }
                        offset = resp.offset || offset;
                        if (resp.status) {
                            $('#run-plan-progress-status').text('Status: ' + resp.status.status);
                            if (resp.status.status === 'completed' || resp.status.status === 'completed_with_errors' || resp.status.status === 'failed' || resp.status.status === 'timeout' || resp.status.status === 'killed') {
                                clearInterval(interval);
                                $('#run-plan-results').show();
                                if (resp.summary) {
                                    $('#run-plan-files').text(resp.summary.files || 0);
                                    var mb = (resp.summary.bytes / (1024*1024)).toFixed(1);
                                    $('#run-plan-size').text(mb + ' MB');
                                    $('#run-plan-time').text((resp.summary.elapsed_seconds || 0) + 's');
                                    $('#run-plan-path').text(resp.summary.path || '');
                                }
                            }
                        }
                    }
                });
            }, 1000);
        }

        // Build read-only tree for a selected named plan
        $('#run-plan-select').on('change', function(){
            var name = $(this).val();
            $('#run-plan-tree').empty();
            if (!name) { $('#run-plan-preview-card').hide(); $('#run-plan-status-card').hide(); return; }
            $('#run-plan-preview-card').show(); $('#run-plan-status-card').show();
            $.post(wgetta_ajax.ajax_url, { action: 'wgetta_plan_load', nonce: $('#wgetta_nonce').val(), name: name }, function(resp){
                if (resp && resp.success && Array.isArray(resp.urls)) {
                    var urls = resp.urls.filter(function(u){ return typeof u === 'string' && u.indexOf(' #SKIP') === -1; });
                    buildReadOnlyTree('#run-plan-tree', '#run-plan-tree-filter', urls);
                }
            });
        });

        function buildReadOnlyTree(treeSel, filterSel, urls) {
            var hosts = {};
            urls.forEach(function(raw){
                try {
                    var a = document.createElement('a'); a.href = raw;
                    var host = a.host || 'root';
                    var parts = (a.pathname || '/').split('/').filter(Boolean);
                    hosts[host] = hosts[host] || { title: host, folder: true, expanded: true, children: [] };
                    var branch = hosts[host];
                    var pathAcc = '';
                    for (var i = 0; i < parts.length; i++) {
                        pathAcc += '/' + parts[i];
                        var found = (branch.children || []).find(function(c){ return c.key === pathAcc; });
                        if (!found) {
                           found = { title: parts[i], key: pathAcc, folder: i < parts.length - 1, children: [] };
                           branch.children.push(found);
                        }
                        branch = found;
                    }
                    // Add leaf node (no checkbox)
                    var leafTitle = a.pathname.replace(/\/$/, '') || a.pathname;
                    branch.children.push({ title: leafTitle, icon: false });
                } catch(e){}
            });
            var source = Object.keys(hosts).map(function(h){ return hosts[h]; });
            var $treeEl = $(treeSel);
            var isInit = $treeEl.hasClass('ui-fancytree') || !!$treeEl.data('ui-fancytree') || !!$treeEl.data('fancytree');
            if (isInit) { try { $treeEl.fancytree('destroy'); } catch(e){} }
            $treeEl.empty();
            $treeEl.fancytree({
                checkbox: false,
                selectMode: 1,
                source: source,
                extensions: ['filter'],
                filter: { autoApply: true, counter: true, fuzzy: false, mode: 'hide' }
            });
            $(filterSel).off('input').on('input', function(){
                var tree = $treeEl.fancytree('getTree');
                tree.filterNodes($(this).val());
            });
        }
    }
    
    function showExecutionResults(data) {
        $('#execution-progress').hide();
        $('#execution-results').show();
        
        $('#files-downloaded').text(data.files || 0);
        $('#total-size').text(data.size || '0 MB');
        $('#time-elapsed').text(data.time || '0s');
        $('#download-path').text(data.path || 'Unknown');
        
        if (data.log) {
            $('#log-location').show();
            $('#log-path').text(data.log);
        }
    }
    
    
    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var html = '<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>';
        $('.wrap h1').after(html);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.notice').fadeOut();
        }, 5000);
    }
    
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function getParameterByName(name) {
        var url = window.location.href;
        name = name.replace(/[\[\]]/g, '\\$&');
        var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)');
        var results = regex.exec(url);
        if (!results) return null;
        if (!results[2]) return '';
        return decodeURIComponent(results[2].replace(/\+/g, ' '));
    }

})(jQuery);