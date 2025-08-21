<?php
/**
 * The core plugin class
 */
class Wgetta {
    
    protected $version;
    protected $plugin_name;
    protected $admin;
    
    public function __construct() {
        $this->version = WGETTA_VERSION;
        $this->plugin_name = 'wgetta';
        
        $this->load_dependencies();
    }
    
    private function load_dependencies() {
        require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-admin.php';
        require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-job-runner.php';
    }
    
    public function run() {
        $this->admin = new Wgetta_Admin($this->plugin_name, $this->version);
        
        // Hook admin actions
        add_action('admin_menu', array($this->admin, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_wgetta_save_settings', array($this->admin, 'ajax_save_settings'));
        add_action('wp_ajax_wgetta_dry_run', array($this->admin, 'ajax_dry_run'));
        add_action('wp_ajax_wgetta_test_regex', array($this->admin, 'ajax_test_regex'));
        add_action('wp_ajax_wgetta_save_regex', array($this->admin, 'ajax_save_regex'));
        add_action('wp_ajax_wgetta_execute', array($this->admin, 'ajax_execute'));
        add_action('wp_ajax_wgetta_log_tail', array($this->admin, 'ajax_log_tail'));
        
        // WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            $this->register_cli_commands();
        }
        
        // Cron job for background execution
        add_action('wgetta_execute_job', array($this, 'execute_background_job'), 10, 2);
    }
    
    /**
     * Register WP-CLI commands
     */
    private function register_cli_commands() {
        WP_CLI::add_command('wgetta', array($this, 'wgetta_cli_command'));
    }
    
    /**
     * Main WP-CLI command handler
     */
    public function wgetta_cli_command($args, $assoc_args) {
        if (empty($args)) {
            WP_CLI::error('Please specify a subcommand: dry-run or exec');
        }
        
        $subcommand = $args[0];
        
        switch ($subcommand) {
            case 'dry-run':
                $this->cli_dry_run($args, $assoc_args);
                break;
            case 'exec':
                $this->cli_execute($args, $assoc_args);
                break;
            default:
                WP_CLI::error("Unknown subcommand: $subcommand. Available: dry-run, exec");
        }
    }
    
    /**
     * WP-CLI dry-run command
     */
    public function cli_dry_run($args, $assoc_args) {
        $cmd = get_option('wgetta_cmd', '');
        
        if (empty($cmd)) {
            WP_CLI::error('No wget command configured. Use the admin interface to set a command.');
        }
        
        try {
            require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-job-runner.php';
            
            // Prepare argv
            $argv = Wgetta_Job_Runner::wgetta_prepare_argv_or_die($cmd);
            
            // Make dry-run argv
            $argv = Wgetta_Job_Runner::wgetta_make_dryrun_argv($argv);
            
            WP_CLI::line('Starting dry-run with command: ' . implode(' ', $argv));
            
            // Create and run job
            $runner = new Wgetta_Job_Runner();
            $job_id = $runner->create_job('dry-run');
            
            WP_CLI::line('Job ID: ' . $job_id);
            
            // Run the job
            $runner->run($argv);
            
            // Extract URLs
            $urls = $runner->extract_urls();
            
            WP_CLI::success('Dry-run completed. Found ' . count($urls) . ' URLs.');
            
            if (count($urls) > 0) {
                WP_CLI::line("\nFirst 10 URLs:");
                foreach (array_slice($urls, 0, 10) as $url) {
                    WP_CLI::line('  - ' . $url);
                }
            }
            
        } catch (Exception $e) {
            WP_CLI::error('Dry-run failed: ' . $e->getMessage());
        }
    }
    
    /**
     * WP-CLI execute command
     */
    public function cli_execute($args, $assoc_args) {
        $cmd = get_option('wgetta_cmd', '');
        
        if (empty($cmd)) {
            WP_CLI::error('No wget command configured. Use the admin interface to set a command.');
        }
        
        try {
            require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-job-runner.php';
            
            // Prepare argv
            $argv = Wgetta_Job_Runner::wgetta_prepare_argv_or_die($cmd);
            
            // Apply saved regex patterns (CLI parity with UI)
            $patterns = get_option('wgetta_regex_patterns', array());
            if (is_array($patterns)) {
                $patterns = array_map('trim', $patterns);
            }
            if (!empty($patterns)) {
                foreach ($patterns as $p) {
                    if ($p === '') { continue; }
                    $v = Wgetta_Job_Runner::wgetta_validate_pattern($p);
                    if (!$v['ok']) {
                        WP_CLI::error('Invalid exclusion pattern "' . $p . '": ' . $v['error']);
                    }
                }
                // Build combined POSIX ERE pattern
                $combined_pattern = '(' . implode(')|(', $patterns) . ')';
                $argv[] = '--reject-regex=' . $combined_pattern;
                WP_CLI::line('Applied regex exclusions: ' . $combined_pattern);
            }
            
            WP_CLI::line('Starting execution with command: ' . implode(' ', $argv));
            
            // Create and run job
            $runner = new Wgetta_Job_Runner();
            $job_id = $runner->create_job('execute');
            
            WP_CLI::line('Job ID: ' . $job_id);
            
            // Run the job
            $runner->run($argv);
            
            // Generate manifest
            $files = $runner->generate_manifest();
            
            $status = $runner->get_status();
            $label = ($status && isset($status['status']) && $status['status'] !== 'completed') ? 'Execution completed with warnings' : 'Execution completed';
            WP_CLI::success($label . '. Downloaded ' . count($files) . ' files.');
            
            if (count($files) > 0) {
                WP_CLI::line("\nFirst 10 files:");
                foreach (array_slice($files, 0, 10) as $file) {
                    WP_CLI::line('  - ' . $file);
                }
            }
            
        } catch (Exception $e) {
            WP_CLI::error('Execution failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Background job executor
     */
    public function execute_background_job($job_id, $argv) {
        try {
            require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-job-runner.php';
            
            $runner = new Wgetta_Job_Runner();
            $runner->set_job($job_id);
            
            // Execute the job
            $runner->run($argv);
            
            // Generate manifest for successful execution
            $runner->generate_manifest();
            
        } catch (Exception $e) {
            // Job will be marked as failed by the runner
            error_log('Wgetta background job failed: ' . $e->getMessage());
        }
    }
}