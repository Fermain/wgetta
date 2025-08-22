<?php
// Prevent direct access
if (!defined('ABSPATH')) { exit; }

$wgetta_cmd = get_option('wgetta_cmd', '');
?>

<div class="wrap">
    <h1>Plan Copy - Execute from Planned List</h1>

    <div class="wgetta-copy-container">
        <div class="wgetta-card">
            <details id="step-command" open>
                <summary><h2>1) Command & Generate</h2></summary>
                <p>Enter your base <code>wget</code> command. Discovery will run with <code>--spider</code>.</p>
                <textarea id="wget-command" name="wgetta_cmd" rows="4" class="large-text code" placeholder="wget -nv --recursive http://example.com/"><?php echo esc_textarea($wgetta_cmd); ?></textarea>
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:10px;">
                    <button type="button" id="generate-plan" class="button button-primary">Generate New Plan</button>
                    <select id="load-plan-select" class="regular-text" style="min-width:240px;"></select>
                    <button type="button" id="load-plan" class="button">Load Existing Plan</button>
                    <span class="spinner"></span>
                </div>
                <div id="plan-status" class="wgetta-status"></div>
            </details>
        </div>

        <div class="wgetta-card">
            <details id="step-review">
                <summary><h2>2) Review & Edit Plan</h2></summary>
                <p>Checked items will be downloaded. Unchecked items remain in the plan but will be skipped.</p>
                <input type="text" id="plan-tree-filter" class="regular-text" placeholder="Filter tree..." style="margin-bottom:8px; width:100%;" />
                <div id="plan-tree" class="wgetta-url-list" style="max-height:380px; overflow:auto;"></div>
                <div style="margin-top:8px;">
                    <button type="button" id="tree-expand-all" class="button">Expand All</button>
                    <button type="button" id="tree-collapse-all" class="button">Collapse All</button>
                </div>
                <div style="margin-top:10px;">
                    <input type="text" id="plan-name" class="regular-text" placeholder="Plan name" value="plan-<?php echo esc_attr( date('Ymd-His') ); ?>" />
                    <button type="button" id="save-plan-named" class="button button-primary">Save Plan</button>
                </div>
            </details>
        </div>

        <div class="wgetta-card">
            <details id="step-execute">
                <summary><h2>3) Execute Plan</h2></summary>
                <button type="button" id="execute-plan" class="button button-primary">Execute Planned URLs</button>
                <span class="spinner"></span>
                <span id="plan-exec-status" class="wgetta-status"></span>
                
                <div id="plan-execution-progress" class="wgetta-progress" style="display:none; margin-top:15px;">
                    <div class="wgetta-progress-info">
                        <span id="plan-progress-status">Queued…</span>
                    </div>
                    <div class="wgetta-output-console" id="plan-output-console" style="margin-top:10px;"></div>
                </div>

                <div id="plan-execution-results" class="wgetta-results" style="display:none; margin-top:15px;">
                    <h3>Execution Complete</h3>
                    <div class="wgetta-stats">
                        <div class="stat-box">
                            <span class="stat-label">Plan:</span>
                            <span class="stat-value" id="plan-name-summary">(unsaved)</span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-label">Files Downloaded:</span>
                            <span class="stat-value" id="plan-files-downloaded">0</span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-label">Total Size:</span>
                            <span class="stat-value" id="plan-total-size">0 MB</span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-label">Time Elapsed:</span>
                            <span class="stat-value" id="plan-time-elapsed">0s</span>
                        </div>
                    </div>
                    <div class="wgetta-download-location">
                        <h4>Download Location:</h4>
                        <code id="plan-download-path"></code>
                    </div>
                </div>
            </details>
        </div>

        <div class="wgetta-card wgetta-history-section">
            <details id="step-history">
                <summary><h2>Recent Executions</h2></summary>
                <div id="plan-execution-history" class="wgetta-history"><p>No previous executions found.</p></div>
            </details>
        </div>
    </div>

    <?php wp_nonce_field('wgetta_ajax_nonce', 'wgetta_nonce'); ?>
</div>

