<?php
if (!defined('ABSPATH')) { exit; }
?>

<div class="wrap">
    <h1>Run Plan</h1>

    <div class="wgetta-card">
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <select id="run-plan-select" class="regular-text" style="min-width:260px;"></select>
            <button type="button" id="run-plan-execute" class="button button-primary">Run Plan</button>
            <span class="spinner"></span>
        </div>
        <div id="run-plan-status" class="wgetta-status"></div>
    </div>

    <div class="wgetta-card">
        <h2>Status</h2>
        <div id="run-plan-progress" class="wgetta-progress" style="display:none;">
            <div class="wgetta-progress-info">
                <span id="run-plan-progress-status">Idle</span>
            </div>
            <div class="wgetta-output-console" id="run-plan-console" style="margin-top:10px;"></div>
        </div>
        <div id="run-plan-results" class="wgetta-results" style="display:none;">
            <div class="wgetta-stats">
                <div class="stat-box">
                    <span class="stat-label">Files Downloaded:</span>
                    <span class="stat-value" id="run-plan-files">0</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Total Size:</span>
                    <span class="stat-value" id="run-plan-size">0 MB</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Time Elapsed:</span>
                    <span class="stat-value" id="run-plan-time">0s</span>
                </div>
            </div>
            <div class="wgetta-download-location">
                <h4>Download Location:</h4>
                <code id="run-plan-path"></code>
            </div>
        </div>
    </div>

    <?php wp_nonce_field('wgetta_ajax_nonce', 'wgetta_nonce'); ?>
</div>

