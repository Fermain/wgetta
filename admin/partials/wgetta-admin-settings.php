<?php
/**
 * Commands page template - Single wget command configuration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get saved command
$wgetta_cmd = get_option('wgetta_cmd', '');
?>

<div class="wrap">
    <h1>Commands</h1>
    
    <div class="wgetta-settings-container">
        <form id="wgetta-settings-form" class="wgetta-form">
            
            <div class="wgetta-card">
                <h2>Wget Command</h2>
                
                <p class="description" style="margin-bottom: 15px;">
                    <strong>Tip:</strong> For cleaner output during dry-runs, consider including <code>-nv</code> (non-verbose) in your command.
                </p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wget-command">Command</label>
                        </th>
                        <td>
                            <textarea 
                                id="wget-command" 
                                name="wgetta_cmd" 
                                rows="6" 
                                cols="80"
                                class="large-text code"
                                placeholder="wget -nv --recursive --level=2 https://example.com"
                            ><?php echo esc_textarea($wgetta_cmd); ?></textarea>
                            <p class="description">
                                Enter your complete wget command. This exact command will be used for execution.
                                <br>For dry-runs in the Plan page, <code>--spider</code> will be automatically injected.
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p class="submit">
                <button type="submit" class="button button-primary" id="save-settings">
                    Save Command
                </button>
                <span class="spinner"></span>
            </p>
            
            <?php wp_nonce_field('wgetta_settings_nonce', 'wgetta_settings_nonce'); ?>
        </form>
    </div>
    
    <div class="wgetta-sidebar">
        <div class="wgetta-card">
            <h3>How It Works</h3>
            <ul style="margin-top: 10px;">
                <li><strong>Commands page:</strong> Your authoritative wget command</li>
                <li><strong>Plan page:</strong> Runs with <code>--spider</code> added (dry-run)</li>
                <li><strong>Copy page:</strong> Executes your command as-is</li>
            </ul>
        </div>
        
        <div class="wgetta-card">
            <h3>Security</h3>
            <p>Commands are parsed and executed safely without shell interpretation. Only wget binary is allowed.</p>
        </div>
    </div>
</div>