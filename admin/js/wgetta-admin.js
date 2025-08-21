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
                    $('#download-path').text(summary.path);
                }
            }

            // Populate simple history list if available
            if (Array.isArray(history)) {
                var html = '';
                for (var i = 0; i < history.length; i++) {
                    var h = history[i];
                    html += '<div class="history-item">' +
                        '<strong>' + (h.id || '') + '</strong>: ' + (h.status || 'unknown') +
                        ' — files: ' + (h.files || 0) +
                        '<br/><code>' + escapeHtml(h.path || '') + '</code>' +
                        '</div>';
                }
                if (html) {
                    $('#execution-history').html(html);
                }
            }
        }
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