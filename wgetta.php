<?php
/**
 * Plugin Name: Wgetta
 * Plugin URI: https://github.com/fermain/wgetta
 * Description: WordPress plugin for managing wget commands with dry-run and regex filtering capabilities
 * Version: 1.0.0
 * Author: fermain
 * License: GPL v2 or later
 * Text Domain: wgetta
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WGETTA_VERSION', '1.0.0');
define('WGETTA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WGETTA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WGETTA_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The core plugin class
 */
require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta.php';

/**
 * Begins execution of the plugin
 */
function run_wgetta() {
    $plugin = new Wgetta();
    $plugin->run();
}

// Hook into WordPress
add_action('plugins_loaded', 'run_wgetta');

/**
 * Activation hook
 */
register_activation_hook(__FILE__, 'wgetta_activate');
function wgetta_activate() {
    $missing_deps = array();

    // Prefer a PATH that includes common Homebrew and system locations
    $paths = '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin';

    if (function_exists('exec')) {
        // Check for wget availability
        exec("env PATH=$paths which wget 2>&1", $output, $return_var);
        if ($return_var !== 0) {
            $missing_deps[] = 'wget';
        }

        // Check for grep availability
        exec("env PATH=$paths which grep 2>&1", $output, $return_var);
        if ($return_var !== 0) {
            $missing_deps[] = 'grep';
        }

        // Check for find availability
        exec("env PATH=$paths which find 2>&1", $output, $return_var);
        if ($return_var !== 0) {
            $missing_deps[] = 'find';
        }
    } else {
        // exec() disabled; treat as missing unless explicitly skipped
        $missing_deps = array('wget', 'grep', 'find');
    }

    if (!empty($missing_deps) && !(defined('WGETTA_SKIP_DEP_CHECKS') && WGETTA_SKIP_DEP_CHECKS)) {
        wp_die(
            'This plugin requires the following command-line tools to be installed: ' .
            implode(', ', $missing_deps) . '. Please install them and try again.',
            'Missing Dependencies',
            array('back_link' => true)
        );
    }
    
    // Initialize default command option (safe default uses --spider)
    $default_cmd = 'wget -nv --spider --recursive --level=1 ' . home_url('/');
    add_option('wgetta_cmd', $default_cmd);
}

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, 'wgetta_deactivate');
function wgetta_deactivate() {
    // Clean up transients
    delete_transient('wgetta_dry_run_results');
}