<?php
/**
 * Copy page template - Execute wget command
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
    <h1>Copy - Execute Wget Command</h1>
    
    <div class="wgetta-copy-container">
        
        <!-- Pre-execution Review -->
        <div class="wgetta-card wgetta-review-section">
            <h2>Command Review</h2>
            
            <?php if (empty($wgetta_cmd)): ?>
                <div class="notice notice-error">
                    <p>No wget command configured. Please <a href="<?php echo admin_url('admin.php?page=wgetta'); ?>">configure your wget command</a> first.</p>
                </div>
            <?php else: ?>
                <div class="wgetta-command-review">
                    <h3>Base Command:</h3>
                    <code class="wgetta-code-block"><?php echo esc_html(trim($wgetta_cmd)); ?></code>
                    
                    <?php if (!empty($regex_patterns)): ?>
                        <h3>Active Exclusion Patterns:</h3>
                        <ul class="wgetta-pattern-list">
                            <?php foreach ($regex_patterns as $pattern): ?>
                                <li><code><?php echo esc_html($pattern); ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <div class="wgetta-final-command">
                        <h3>Final Command:</h3>
                        <code class="wgetta-code-block" id="final-execution-command"><?php 
                            // Build final command with POSIX ERE regex patterns
                            $final_command = $wgetta_cmd;
                            if (!empty($regex_patterns)) {
                                // Use --reject-regex with POSIX ERE patterns
                                $combined_pattern = '(' . implode(')|(', $regex_patterns) . ')';
                                $final_command .= " --reject-regex='" . $combined_pattern . "'";
                            }
                            echo esc_html(trim($final_command));
                        ?></code>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Execution Options -->
        <div class="wgetta-card wgetta-options-section">
            <h2>Execution Options</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="download-directory">Download Directory</label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            id="download-directory" 
                            name="download_directory" 
                            class="regular-text"
                            value="<?php echo esc_attr(wp_upload_dir()['basedir'] . '/wgetta-downloads'); ?>"
                        />
                        <p class="description">Where to save downloaded files</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="create-log">Create Log File</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="create-log" name="create_log" value="1" checked />
                            Save detailed log of the operation
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="background-mode">Background Mode</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="background-mode" name="background_mode" value="1" />
                            Run in background (for large downloads)
                        </label>
                        <p class="description">Background mode allows you to close this page while the download continues</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Execution Control -->
        <div class="wgetta-card wgetta-execution-section">
            <h2>Execute Command</h2>
            
            <div class="wgetta-warning notice notice-warning">
                <p><strong>Warning:</strong> This will execute the wget command and download files to your server. Make sure you have:</p>
                <ul>
                    <li>Sufficient disk space</li>
                    <li>Permission to download from the target site</li>
                    <li>Reviewed the command and exclusion patterns</li>
                </ul>
            </div>
            
            <div class="wgetta-execution-controls">
                <button type="button" id="execute-command" class="button button-primary button-large" <?php echo empty($wgetta_cmd) ? 'disabled' : ''; ?>>
                    Execute Wget Command
                </button>
                <button type="button" id="cancel-execution" class="button button-large" style="display:none;">
                    Cancel
                </button>
                <span class="spinner"></span>
            </div>
            
            <!-- Progress Display -->
            <div id="execution-progress" class="wgetta-progress" style="display:none;">
                <h3>Execution Progress</h3>
                <div class="wgetta-progress-bar">
                    <div class="wgetta-progress-fill" id="progress-fill"></div>
                </div>
                <div class="wgetta-progress-info">
                    <span id="progress-status">Initializing...</span>
                    <span id="progress-percentage">0%</span>
                </div>
            </div>
            
            <!-- Output Display -->
            <div id="execution-output" class="wgetta-output" style="display:none;">
                <h3>Command Output</h3>
                <div class="wgetta-output-console" id="output-console">
                    <!-- Real-time output will be displayed here -->
                </div>
            </div>
            
            <!-- Results Summary -->
            <div id="execution-results" class="wgetta-results" style="display:none;">
                <h3>Execution Complete</h3>
                
                <div class="wgetta-stats">
                    <div class="stat-box">
                        <span class="stat-label">Files Downloaded:</span>
                        <span class="stat-value" id="files-downloaded">0</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-label">Total Size:</span>
                        <span class="stat-value" id="total-size">0 MB</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-label">Time Elapsed:</span>
                        <span class="stat-value" id="time-elapsed">0s</span>
                    </div>
                </div>
                
                <div class="wgetta-download-location">
                    <h4>Download Location:</h4>
                    <code id="download-path"></code>
                </div>
                
                <div class="wgetta-log-location" id="log-location" style="display:none;">
                    <h4>Log File:</h4>
                    <code id="log-path"></code>
                    <button type="button" class="button" id="view-log">View Log</button>
                </div>
                
                <div class="wgetta-actions">
                    <button type="button" class="button" id="new-execution">
                        New Execution
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=wgetta'); ?>" class="button">
                        Back to Settings
                    </a>
                </div>
            </div>
        </div>
        
        <!-- History Section -->
        <div class="wgetta-card wgetta-history-section">
            <h2>Recent Executions</h2>
            
            <div id="execution-history" class="wgetta-history">
                <p>No previous executions found.</p>
                <!-- History will be populated here -->
            </div>
        </div>
    </div>
    
    <?php wp_nonce_field('wgetta_ajax_nonce', 'wgetta_nonce'); ?>
</div>