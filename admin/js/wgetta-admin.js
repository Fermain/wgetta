/**
 * Wgetta Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Initialize based on current page
        var currentPage = getParameterByName('page');
        
        if (currentPage === 'wgetta') {
            initSettingsPage();
        } else if (currentPage === 'wgetta-plan') {
            initPlanPage();
        } else if (currentPage === 'wgetta-copy') {
            initCopyPage();
        } else if (currentPage === 'wgetta-plan-copy') {
            initPlanCopyPage();
        } else if (currentPage === 'wgetta-plan-run') {
            initPlanRunPage();
        }
        
    });
    
    /**
     * Settings Page Functions
     */
    function initSettingsPage() {
        
        // Handle settings form submission
        $('#wgetta-settings-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $spinner = $form.find('.spinner');
            var $button = $('#save-settings');
            
            $spinner.addClass('is-active');
            $button.prop('disabled', true);
            
            var data = {
                action: 'wgetta_save_settings',
                nonce: wgetta_ajax.nonce,
                wgetta_cmd: $('#wget-command').val()
            };
            
            $.post(wgetta_ajax.ajax_url, data, function(response) {
                $spinner.removeClass('is-active');
                $button.prop('disabled', false);
                
                if (response.success) {
                    showNotice('success', response.message);
                } else {
                    showNotice('error', response.message || 'Failed to save command');
                }
            });
        });
    }
    
    /**
     * Plan Page Functions
     */
    function initPlanPage() {
        var dryRunUrls = [];
        var currentJobId = null;
        
        // Handle dry run
        $('#run-dry-run').on('click', function() {
            var $button = $(this);
            var $spinner = $button.siblings('.spinner');
            var $status = $('#dry-run-status');
            
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $status.text('Starting dry run...').removeClass('success error');
            
            var data = {
                action: 'wgetta_dry_run',
                nonce: $('#wgetta_nonce').val()
            };
            
            $.post(wgetta_ajax.ajax_url, data, function(response) {
                $spinner.removeClass('is-active');
                $button.prop('disabled', false);
                
                if (response.success) {
                    $status.text('Dry run complete!').addClass('success');
                    
                    // Store job ID and URLs
                    currentJobId = response.job_id;
                    dryRunUrls = response.data.urls || [];
                    
                    // Display results
                    displayDryRunResults(dryRunUrls);
                    
                    // Enable regex testing
                    $('#regex-test-container').show();
                    $('#regex-no-urls').hide();
                    
                    // Store job ID in data attribute for regex testing
                    $('#test-regex').data('job-id', currentJobId);
                    
                } else {
                    $status.text('Dry run failed: ' + (response.message || 'Unknown error')).addClass('error');
                    $('#dry-run-errors').show().find('#dry-run-error-list').html(response.message || 'Unknown error occurred');
                }
            }).fail(function() {
                $spinner.removeClass('is-active');
                $button.prop('disabled', false);
                $status.text('Network error occurred').addClass('error');
            });
        });
        
        // Handle adding regex patterns
        $('#add-regex-pattern').on('click', function() {
            var patternHtml = '<div class="wgetta-regex-pattern">' +
                '<input type="text" class="regular-text wgetta-regex-input" placeholder="Enter regex pattern" />' +
                '<button type="button" class="button wgetta-remove-pattern">Remove</button>' +
                '</div>';
            $('#regex-patterns-list').append(patternHtml);
        });
        
        // Handle removing regex patterns
        $(document).on('click', '.wgetta-remove-pattern', function() {
            $(this).parent('.wgetta-regex-pattern').remove();
        });
        
        // Handle preset regex patterns (supports multiple patterns separated by ||)
        $('.wgetta-preset-regex').on('click', function() {
            var combined = $(this).data('pattern');
            var patterns = (combined && typeof combined === 'string') ? combined.split('||') : [];
            if (patterns.length === 0 && combined) {
                patterns = [combined];
            }
            if (patterns.length === 0) return;
            for (var i = 0; i < patterns.length; i++) {
                var p = patterns[i];
                var $lastInput = $('.wgetta-regex-input:last');
                if ($lastInput.val()) {
                    $('#add-regex-pattern').click();
                    $('.wgetta-regex-input:last').val(p);
                } else {
                    $lastInput.val(p);
                }
            }
        });
        
        // Handle regex testing with POSIX ERE
        $('#test-regex').on('click', function() {
            var jobId = $(this).data('job-id');
            
            if (!jobId) {
                alert('Please run a dry run first to get URLs for testing');
                return;
            }
            
            var patterns = [];
            $('.wgetta-regex-input').each(function() {
                var val = $(this).val().trim();
                if (val) {
                    patterns.push(val);
                }
            });
            
            if (patterns.length === 0) {
                alert('Please enter at least one regex pattern');
                return;
            }
            
            var $spinner = $(this).siblings('.spinner');
            $spinner.addClass('is-active');
            
            // Send to server for POSIX ERE testing
            var data = {
                action: 'wgetta_test_regex',
                nonce: $('#wgetta_nonce').val(),
                job_id: jobId,
                patterns: patterns
            };
            
            $.post(wgetta_ajax.ajax_url, data, function(response) {
                $spinner.removeClass('is-active');
                
                if (response.success) {
                    displayRegexResults(response.included, response.excluded);
                } else {
                    alert('Regex testing failed: ' + (response.message || 'Unknown error'));
                }
            });
        });
        
        // Handle saving regex patterns
        $('#save-regex').on('click', function() {
            var patterns = [];
            $('.wgetta-regex-input').each(function() {
                var val = $(this).val().trim();
                if (val) {
                    patterns.push(val);
                }
            });
            
            var data = {
                action: 'wgetta_save_regex',
                nonce: $('#wgetta_nonce').val(),
                patterns: patterns
            };
            
            $.post(wgetta_ajax.ajax_url, data, function(response) {
                if (response.success) {
                    showNotice('success', 'Regex patterns saved successfully');
                } else {
                    showNotice('error', 'Failed to save patterns');
                }
            });
        });
    }
    
    /**
     * Copy Page Functions
     */
    function initCopyPage() {
        var logPollingInterval = null;
        var currentJobId = null;
        var logOffset = 0;

        // Load existing history immediately
        fetchHistory();
        
        // Handle command execution
        $('#execute-command').on('click', function() {
            if (!confirm('Are you sure you want to execute this wget command? This will download files to your server.')) {
                return;
            }
            
            var $button = $(this);
            var $cancel = $('#cancel-execution');
            var $spinner = $button.siblings('.spinner');
            
            $button.hide();
            $cancel.show();
            $spinner.addClass('is-active');
            
            // Show progress section
            $('#execution-progress').show();
            $('#execution-output').show();
            
            // Start execution and get job ID
            var data = {
                action: 'wgetta_execute',
                nonce: $('#wgetta_nonce').val()
            };
            
            $.post(wgetta_ajax.ajax_url, data, function(response) {
                if (response.success) {
                    currentJobId = response.job_id;
                    logOffset = 0;
                    
                    // Start polling for log updates
                    startLogPolling(currentJobId);
                } else {
                    alert('Failed to start execution: ' + (response.message || 'Unknown error'));
                    $button.show();
                    $cancel.hide();
                    $spinner.removeClass('is-active');
                }
            });
        });
        
        // Function to poll log tail
        function startLogPolling(jobId) {
            logPollingInterval = setInterval(function() {
                var data = {
                    action: 'wgetta_log_tail',
                    nonce: $('#wgetta_nonce').val(),
                    job_id: jobId,
                    offset: logOffset
                };
                
                $.post(wgetta_ajax.ajax_url, data, function(response) {
                    if (response.success) {
                        // Append new log content
                        if (response.content) {
                            var $console = $('#output-console');
                            $console.append(escapeHtml(response.content));
                            $console.scrollTop($console[0].scrollHeight);
                        }
                        
                        // Update offset
                        logOffset = response.offset;
                        
                        // Check job status
                        if (response.status) {
                            $('#progress-status').text('Status: ' + response.status.status);
                            
                            if (response.status.status === 'completed' || 
                                response.status.status === 'completed_with_errors' ||
                                response.status.status === 'failed' || 
                                response.status.status === 'timeout' ||
                                response.status.status === 'killed') {
                                // Stop polling
                                clearInterval(logPollingInterval);
                                
                                // Update UI
                                $('#execute-command').show();
                                $('#cancel-execution').hide();
                                $('.spinner').removeClass('is-active');
                                
                                // Show results
                                showExecutionComplete(response.status, response.summary, response.history);
                            }
                        }
                    }
                });
            }, 1000); // Poll every second
        }
        
        // Handle cancel
        $('#cancel-execution').on('click', function() {
            if (logPollingInterval) {
                clearInterval(logPollingInterval);
            }
            alert('Cancellation not yet implemented - polling stopped');
        });
        
        // Handle new execution
        $('#new-execution').on('click', function() {
            location.reload();
        });
        
        function showExecutionComplete(status, summary, history) {
            $('#execution-results').show();
            
            if (status.status === 'completed' || status.status === 'completed_with_errors') {
                showNotice('success', 'Wget execution completed successfully');
            } else {
                showNotice('error', 'Wget execution ' + status.status);
            }

            if (summary) {
                // Update stats if available
                if (typeof summary.files !== 'undefined') {
                    $('#files-downloaded').text(summary.files);
                }
                if (typeof summary.bytes !== 'undefined') {
                    var mb = (summary.bytes / (1024 * 1024)).toFixed(1);
                    $('#total-size').text(mb + ' MB');
                }
                if (typeof summary.elapsed_seconds !== 'undefined') {
                    $('#time-elapsed').text(summary.elapsed_seconds + 's');
                }
                if (summary.path) {
                    var $el = $('#download-path');
                    var pathText = summary.path;
                    // If we have a zip, make the whole path clickable
                    if (summary.zip_url) {
                        var anchorHtml = '<a href="' + summary.zip_url + '" target="_blank"><code id="download-path">' + escapeHtml(pathText) + '</code></a>';
                        $el.replaceWith(anchorHtml);
                    } else {
                        $el.text(pathText);
                    }
                }
            }

            // Populate history table
            if (Array.isArray(history)) {
                var html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Job</th><th>Status</th><th>Files</th><th>Path</th><th>Archive</th><th>Actions</th></tr></thead><tbody>';
                for (var i = 0; i < history.length; i++) {
                    var h = history[i];
                    html += '<tr>' +
                        '<td><code>' + (h.id || '') + '</code></td>' +
                        '<td>' + (h.status || 'unknown') + '</td>' +
                        '<td>' + (h.files || 0) + '</td>' +
                        '<td><code>' + escapeHtml(h.path || '') + '</code></td>' +
                        '<td>' + (h.zip_url ? ('<a href="' + h.zip_url + '" target="_blank">Download</a>') : '') + '</td>' +
                        '<td><button class="button link-delete" data-job="' + (h.id || '') + '">Remove</button></td>' +
                        '</tr>';
                }
                html += '</tbody></table>';
                $('#execution-history').html(html);
            }
        }

        function fetchHistory() {
            var data = {
                action: 'wgetta_history',
                nonce: $('#wgetta_nonce').val()
            };
            $.post(wgetta_ajax.ajax_url, data, function(response) {
                if (response && response.success && Array.isArray(response.history)) {
                    var html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Job</th><th>Status</th><th>Files</th><th>Path</th><th>Archive</th><th>Actions</th></tr></thead><tbody>';
                    for (var i = 0; i < response.history.length; i++) {
                        var h = response.history[i];
                        html += '<tr>' +
                            '<td><code>' + (h.id || '') + '</code></td>' +
                            '<td>' + (h.status || 'unknown') + '</td>' +
                            '<td>' + (h.files || 0) + '</td>' +
                            '<td><code>' + escapeHtml(h.path || '') + '</code></td>' +
                            '<td>' + (h.zip_url ? ('<a href="' + h.zip_url + '" target="_blank">Download</a>') : '') + '</td>' +
                            '<td><button class="button link-delete" data-job="' + (h.id || '') + '">Remove</button></td>' +
                            '</tr>';
                    }
                    html += '</tbody></table>';
                    $('#execution-history').html(html);
                }
            });
        }

        // Delegate remove clicks
        $(document).on('click', '.link-delete', function(e){
            e.preventDefault();
            if (!confirm('Remove this job and all downloaded files?')) { return; }
            var $btn = $(this);
            var job = $btn.data('job');
            $.post(wgetta_ajax.ajax_url, { action: 'wgetta_job_remove', nonce: $('#wgetta_nonce').val(), job_id: job }, function(resp){
                if (resp && resp.success) {
                    $btn.closest('tr').fadeOut(200, function(){ $(this).remove(); });
                } else {
                    alert((resp && resp.message) ? resp.message : 'Failed to remove job');
                }
            });
        });
    }
    
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
            $btn.prop('disabled', true); $spin.addClass('is-active'); $status.text('Generating plan...');
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
                    $status.text('Plan generated');
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
                showNotice(resp && resp.success ? 'success' : 'error', resp && resp.message ? resp.message : 'Save failed');
                loadNamedPlans();
            });
        });

        $('#load-plan').on('click', function() {
            var name = $('#load-plan-select').val();
            if (!name) return;
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
                        }
                    });
                } else {
                    showNotice('error', (createResp && createResp.message) ? createResp.message : 'Failed to prepare plan');
                }
            });
        });

        $('#execute-plan').on('click', function() {
            var $btn = $(this), $spin = $btn.siblings('.spinner');
            var $status = $('#plan-exec-status');
            // Auto-save current selections to plan.csv before executing
            var urls = collectPlan();
            var planName = $('#plan-name').val().trim();
            var includedCount = 0; for (var i = 0; i < urls.length; i++) { if (!/\s+#SKIP$/.test(urls[i])) includedCount++; }
            $.post(wgetta_ajax.ajax_url, {
                action: 'wgetta_plan_save',
                nonce: $('#wgetta_nonce').val(),
                job_id: currentJobId,
                urls: urls
            }, function(saveResp) {
                // Set job metadata before execution
                $.post(wgetta_ajax.ajax_url, {
                    action: 'wgetta_set_job_meta',
                    nonce: $('#wgetta_nonce').val(),
                    job_id: currentJobId,
                    meta: { plan_name: planName || '(unsaved)', urls_included: includedCount, urls_total: urls.length }
                }, function(){
                    // Regardless of metadata save, attempt to execute and show any errors
                    $.post(wgetta_ajax.ajax_url, {
                        action: 'wgetta_plan_execute',
                        nonce: $('#wgetta_nonce').val(),
                        job_id: currentJobId
                    }, function(resp) {
                        if (resp && resp.success) {
                            $status.text('Plan execution queued (Job ' + resp.job_id + ').');
                            $('#plan-execution-progress').show();
                            $('#plan-progress-status').text('Queued');
                            $('#plan-output-console').text('');
                            startPlanLogPolling(resp.job_id, planName);
                            try { document.getElementById('step-execute').open = true; } catch(e){}
                        } else {
                            $status.text((resp && resp.message) ? resp.message : 'Failed to execute plan');
                        }
                    });
                });
            });
        });

        function startPlanLogPolling(jobId, planName) {
            var offset = 0;
            var interval = setInterval(function(){
                $.post(wgetta_ajax.ajax_url, {
                    action: 'wgetta_log_tail',
                    nonce: $('#wgetta_nonce').val(),
                    job_id: jobId,
                    offset: offset
                }, function(response){
                    if (response && response.success) {
                        if (response.content) {
                            var $console = $('#plan-output-console');
                            $console.append(escapeHtml(response.content));
                            $console.scrollTop($console[0].scrollHeight);
                        }
                        offset = response.offset || offset;
                        if (response.status) {
                            $('#plan-progress-status').text('Status: ' + response.status.status);
                            if (response.status.status === 'completed' || response.status.status === 'completed_with_errors' || response.status.status === 'failed' || response.status.status === 'timeout' || response.status.status === 'killed') {
                                clearInterval(interval);
                                $('#plan-execution-results').show();
                                $('#plan-name-summary').text(planName || '(unsaved)');
                                if (response.summary) {
                                    $('#plan-files-downloaded').text(response.summary.files || 0);
                                    var mb = (response.summary.bytes / (1024*1024)).toFixed(1);
                                    $('#plan-total-size').text(mb + ' MB');
                                    $('#plan-time-elapsed').text((response.summary.elapsed_seconds || 0) + 's');
                                    $('#plan-download-path').text(response.summary.path || '');
                                    if (response.summary.zip_url) {
                                        $('#plan-download-path').after(' — <a href="' + response.summary.zip_url + '" target="_blank">Download ZIP</a>');
                                    }
                                }
                                // History (render as WP table)
                                if (Array.isArray(response.history)) {
                                    var html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Job</th><th>Status</th><th>Files</th><th>Path</th><th>Archive</th></tr></thead><tbody>';
                                    for (var i = 0; i < response.history.length; i++) {
                                        var h = response.history[i];
                                        html += '<tr>' +
                                            '<td><code>' + (h.id || '') + '</code></td>' +
                                            '<td>' + (h.status || 'unknown') + '</td>' +
                                            '<td>' + (h.files || 0) + '</td>' +
                                            '<td><code>' + escapeHtml(h.path || '') + '</code></td>' +
                                            '<td>' + (h.zip_url ? ('<a href="' + h.zip_url + '" target="_blank">Download</a>') : '') + '</td>' +
                                            '</tr>';
                                    }
                                    html += '</tbody></table>';
                                    $('#plan-execution-history').html(html);
                                    try { document.getElementById('step-history').open = true; } catch(e){}
                                }
                            }
                        }
                    }
                });
            }, 1000);
        }

        function buildTreeAndList(urls) {
            // Build FancyTree data: one node per host, children per path segment as folders, with leaves per URL
            var hosts = {};
            var skipSet = {};
            urls.forEach(function(u){
                try {
                    var raw = u;
                    var isSkip = /\s+#SKIP$/.test(raw);
                    if (isSkip) {
                        raw = raw.replace(/\s+#SKIP$/, '');
                        skipSet[raw] = true;
                    }
                    var a = document.createElement('a'); a.href = raw;
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
                    branch.children.push({ title: leafTitle, checkbox: true, data: { url: raw } });
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