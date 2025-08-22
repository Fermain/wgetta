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
        add_action('wp_ajax_wgetta_execute', array($this->admin, 'ajax_execute'));
        add_action('wp_ajax_wgetta_log_tail', array($this->admin, 'ajax_log_tail'));
        add_action('wp_ajax_wgetta_history', array($this->admin, 'ajax_history'));
        add_action('wp_ajax_wgetta_plan_generate', array($this->admin, 'ajax_plan_generate'));
        add_action('wp_ajax_wgetta_plan_save', array($this->admin, 'ajax_plan_save'));
        add_action('wp_ajax_wgetta_plan_execute', array($this->admin, 'ajax_plan_execute'));
        add_action('wp_ajax_wgetta_plan_list', array($this->admin, 'ajax_plan_list'));
        add_action('wp_ajax_wgetta_plan_load', array($this->admin, 'ajax_plan_load'));
        add_action('wp_ajax_wgetta_plan_save_named', array($this->admin, 'ajax_plan_save_named'));
        add_action('wp_ajax_wgetta_plan_create', array($this->admin, 'ajax_plan_create'));
        add_action('wp_ajax_wgetta_git_save', array($this->admin, 'ajax_git_save'));
        add_action('wp_ajax_wgetta_git_deploy_dry', array($this->admin, 'ajax_git_deploy_dry'));
        add_action('wp_ajax_wgetta_git_deploy_push', array($this->admin, 'ajax_git_deploy_push'));
        add_action('wp_ajax_wgetta_git_test', array($this->admin, 'ajax_git_test'));
        add_action('wp_ajax_wgetta_set_job_meta', array($this->admin, 'ajax_set_job_meta'));
        add_action('wp_ajax_wgetta_job_remove', array($this->admin, 'ajax_job_remove'));
        
        // WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            $this->register_cli_commands();
        }
        
        // Cron job for background execution
        add_action('wgetta_execute_job', array($this, 'execute_background_job'), 10, 2);
        add_action('wgetta_execute_plan_job', array($this, 'execute_plan_job'), 10, 1);
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
            // Create downloadable archive
            $runner->create_archive_zip('archive.zip');
            // Persist plan name if provided (for later display/deploy)
            if (!empty($argv) && is_array($argv)) {
                // no-op here; UI can call set_metadata via a dedicated endpoint if needed
            }
            
        } catch (Exception $e) {
            // Job will be marked as failed by the runner
            error_log('Wgetta background job failed: ' . $e->getMessage());
        }
    }

    /**
     * Background plan executor - reads plan.csv and downloads each URL sequentially
     */
    public function execute_plan_job($job_id) {
        try {
            require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-job-runner.php';
            $runner = new Wgetta_Job_Runner();
            $runner->set_job($job_id);

            // Load plan
            $upload_dir = wp_upload_dir();
            $job_dir = $upload_dir['basedir'] . '/wgetta/jobs/' . $job_id;
            $plan_file = $job_dir . '/plan.csv';
            if (!file_exists($plan_file)) {
                throw new RuntimeException('Plan not found');
            }
            $urls = array_filter(array_map('trim', explode("\n", file_get_contents($plan_file))));

            // Prepare base argv from saved command (strip URL tokens and recursion/filter flags)
            $cmd = get_option('wgetta_cmd', '');
            $argv = Wgetta_Job_Runner::wgetta_prepare_argv_or_die($cmd);
            $base = array();
            $remove_no_value = array(
                '--recursive','-r','--mirror','-m','--span-hosts','-H','--spider',
                '--page-requisites','-p','--convert-links','-k','--backup-converted','-K',
                '--delete-after'
            );
            $remove_with_value = array(
                '--level','--domains','--exclude-domains','--accept','--reject',
                '--accept-regex','--reject-regex','--input-file','-i','--adjust-extension','-E'
            );
            for ($i = 0; $i < count($argv); $i++) {
                $tok = $argv[$i];
                // Skip URLs entirely
                if (preg_match('#^https?://#i', $tok)) { continue; }
                // Handle long opts with =value
                $opt_name = $tok;
                if (strpos($tok, '=') !== false && substr($tok, 0, 2) === '--') {
                    $opt_name = substr($tok, 0, strpos($tok, '='));
                }
                // Remove flags that would broaden the download beyond the explicit plan
                if (in_array($tok, $remove_no_value, true) || in_array($opt_name, $remove_no_value, true)) { continue; }
                if (in_array($tok, $remove_with_value, true) || in_array($opt_name, $remove_with_value, true)) {
                    // Skip this option; also skip next token if value provided as separate arg and no '=' was used
                    if (strpos($tok, '=') === false) { $i++; }
                    continue;
                }
                $base[] = $tok;
            }

            // Disable robots fetching to avoid implicit robots.txt download
            $base[] = '-e';
            $base[] = 'robots=off';
            // Ensure directory structure (host/path) is created for each URL
            $base[] = '--force-directories';

            // Execute each URL
            // De-duplicate planned URLs after stripping #SKIP markers
            $seen = array();
            foreach ($urls as $url) {
                // Skip entries marked as '#SKIP'
                if (strpos($url, ' #SKIP') !== false) { continue; }
                $clean = trim($url);
                if (isset($seen[$clean])) { continue; }
                $seen[$clean] = true;
                $runner->run(array_merge($base, array($clean)));
            }

            // Manifest
            $runner->generate_manifest();
            // Create downloadable archive
            $runner->create_archive_zip('archive.zip');
            // Store plan name if provided
        } catch (Exception $e) {
            error_log('Wgetta plan job failed: ' . $e->getMessage());
        }
    }
}