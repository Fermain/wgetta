<?php
// Prevent direct access
if (!defined('ABSPATH')) { exit; }

$wgetta_cmd = get_option('wgetta_cmd', '');
?>

<div class="wrap">
    <h1>Create Plan</h1>

    <div class="wgetta-copy-container">
        <div class="wgetta-card">
            <details id="step-command" open>
                <summary><h2>Create Index</h2></summary>
                <p>Enter your base <code>wget</code> command. Discovery will run with <code>--spider</code>.</p>
                <textarea id="wget-command" name="wgetta_cmd" rows="4" class="large-text code" placeholder="wget -nv --recursive http://example.com/"><?php echo esc_textarea($wgetta_cmd); ?></textarea>
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:10px;">
                    <button type="button" id="generate-plan" class="button button-primary">Generate New Plan</button>
                    <select id="load-plan-select" class="regular-text" style="min-width:240px;"></select>
                    <button type="button" id="delete-plan" class="button" style="display:none;">Delete Plan</button>
                    <span class="spinner"></span>
                </div>
                <div id="plan-status" class="wgetta-status"></div>
            </details>
        </div>

        <div class="wgetta-card">
            <details id="step-review">
                <summary><h2>Save Plan</h2></summary>
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

        

        
    </div>

    <?php wp_nonce_field('wgetta_ajax_nonce', 'wgetta_nonce'); ?>
</div>

