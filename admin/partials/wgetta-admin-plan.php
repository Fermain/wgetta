<?php
/**
 * Plan page template - Dry run and regex testing
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get saved command and patterns
$wgetta_cmd = get_option('wgetta_cmd', '');
$regex_patterns = get_option('wgetta_regex_patterns', array());
?>

<div class="wrap">
    <h1>Plan - Dry Run & Regex Testing</h1>
    
    <div class="wgetta-plan-container">
        
        <!-- Dry Run Section -->
        <div class="wgetta-card wgetta-dry-run-section">
            <h2>Step 1: Dry Run</h2>
            
            <?php if (empty($wgetta_cmd)): ?>
                <div class="notice notice-warning">
                    <p>No wget command configured. Please <a href="<?php echo admin_url('admin.php?page=wgetta'); ?>">configure your wget command</a> first.</p>
                </div>
            <?php else: ?>
                <div class="wgetta-command-display">
                    <h3>Current Command:</h3>
                    <code class="wgetta-code-block"><?php echo esc_html(trim($wgetta_cmd)); ?></code>
                </div>
                <input type="hidden" id="wgetta-base-cmd" value="<?php echo esc_attr(trim($wgetta_cmd)); ?>" />
                
                <div class="wgetta-dry-run-controls">
                    <button type="button" id="run-dry-run" class="button button-primary">
                        Run Dry Run
                    </button>
                    <span class="spinner"></span>
                    <span id="dry-run-status" class="wgetta-status"></span>
                </div>
                
                <div id="dry-run-results" class="wgetta-results" style="display:none;">
                    <h3>Discovered URLs:</h3>
                    <div class="wgetta-url-count">
                        Total URLs found: <span id="url-count">0</span>
                    </div>
                    <div id="dry-run-url-list" class="wgetta-url-list">
                        <!-- URLs will be populated here -->
                    </div>
                </div>
                
                <div id="dry-run-errors" class="wgetta-errors" style="display:none;">
                    <h3>Errors:</h3>
                    <div id="dry-run-error-list" class="wgetta-error-list">
                        <!-- Errors will be populated here -->
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Regex Testing Section -->
        <div class="wgetta-card wgetta-regex-section">
            <h2>Step 2: Regex Exclusion Testing</h2>
            
            <div id="regex-test-container" style="display:none;">
                
                <div class="wgetta-regex-inputs">
                    <h3>Exclusion Patterns (POSIX ERE)</h3>
                    <p class="description">Enter POSIX Extended Regular Expression patterns to exclude URLs:</p>
                    <p class="description" style="color: #666; font-style: italic;">
                        Note: Patterns use POSIX ERE syntax (grep -E), not PCRE. This lab is advisory only - 
                        the actual execution will use the flags specified in your wget command.
                    </p>
                    <p class="description" style="color: #666;">Tip: Escape literal question marks in query strings, e.g. use <code>\?p=</code> not <code>?p=</code>.</p>
                    
                    <div id="regex-patterns-list">
                        <?php if (!empty($regex_patterns)): ?>
                            <?php foreach ($regex_patterns as $pattern): ?>
                                <div class="wgetta-regex-pattern">
                                    <input type="text" class="regular-text wgetta-regex-input" value="<?php echo esc_attr($pattern); ?>" />
                                    <button type="button" class="button wgetta-remove-pattern">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="wgetta-regex-pattern">
                                <input type="text" class="regular-text wgetta-regex-input" placeholder="e.g., \.pdf$" />
                                <button type="button" class="button wgetta-remove-pattern">Remove</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" id="add-regex-pattern" class="button">
                        Add Pattern
                    </button>
                    
                    <div class="wgetta-regex-presets">
                        <h4>Common Patterns:</h4>
                        <button type="button" class="button wgetta-preset-regex" data-pattern="\.pdf$">Exclude PDFs</button>
                        <button type="button" class="button wgetta-preset-regex" data-pattern="\.(jpg|jpeg|png|gif)$">Exclude Images</button>
                        <button type="button" class="button wgetta-preset-regex" data-pattern="\.(mp4|avi|mov|wmv)$">Exclude Videos</button>
                        <button type="button" class="button wgetta-preset-regex" data-pattern="/wp-admin/">Exclude Admin</button>
                        <button type="button" class="button wgetta-preset-regex" data-pattern="/page/||\?p=||\?rev=">Exclude Paging</button>
                    </div>
                </div>
                
                <div class="wgetta-regex-controls">
                    <button type="button" id="test-regex" class="button button-primary">
                        Test Patterns
                    </button>
                    <button type="button" id="save-regex" class="button">
                        Save Patterns
                    </button>
                    <span class="spinner"></span>
                </div>
                
                <!-- Two Column Results -->
                <div id="regex-results" class="wgetta-regex-results" style="display:none;">
                    <div class="wgetta-columns">
                        <div class="wgetta-column wgetta-included">
                            <h3>Included URLs (<span class="included-count">0</span>)</h3>
                            <div class="wgetta-url-list" id="included-urls">
                                <!-- Included URLs will be listed here -->
                            </div>
                        </div>
                        
                        <div class="wgetta-column wgetta-excluded">
                            <h3>Excluded URLs (<span class="excluded-count">0</span>)</h3>
                            <div class="wgetta-url-list" id="excluded-urls">
                                <!-- Excluded URLs will be listed here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="regex-no-urls" class="notice notice-info">
                <p>Run a dry run first to get URLs for regex testing.</p>
            </div>
        </div>
        
        <!-- Summary Section -->
        <div class="wgetta-card wgetta-summary-section" id="plan-summary" style="display:none;">
            <h2>Plan Summary</h2>
            
            <div class="wgetta-summary-stats">
                <div class="stat-box">
                    <span class="stat-label">Total URLs:</span>
                    <span class="stat-value" id="summary-total">0</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Included:</span>
                    <span class="stat-value" id="summary-included">0</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Excluded:</span>
                    <span class="stat-value" id="summary-excluded">0</span>
                </div>
            </div>
            
            <div class="wgetta-final-command">
                <h3>Final Command (with exclusions):</h3>
                <code class="wgetta-code-block" id="final-command"></code>
            </div>
            
            <div class="wgetta-next-step">
                <p>Ready to execute?</p>
                <a href="<?php echo admin_url('admin.php?page=wgetta-copy'); ?>" class="button button-primary button-large">
                    Proceed to Copy
                </a>
            </div>
        </div>
    </div>
    
    <?php wp_nonce_field('wgetta_ajax_nonce', 'wgetta_nonce'); ?>
</div>