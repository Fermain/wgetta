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
        require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-mirrorer.php';
        require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-crawler.php';
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
            WP_CLI::error('No base URL configured. Use the admin interface to set a command.');
        }

        try {
            $runner = new Wgetta_Job_Runner();
            $job_id = $runner->create_job('dry-run');
            WP_CLI::line('Job ID: ' . $job_id);

            // Seeds from command argv
            $argv = Wgetta_Job_Runner::wgetta_prepare_argv_or_die($cmd);
            $seeds = array();
            foreach ($argv as $tok) { if (preg_match('#^https?://#i', $tok)) { $seeds[] = $tok; } }
            if (empty($seeds)) { $seeds = array(home_url('/')); }
            // Also seed /wp-json/ per host to traverse REST routes
            $jsonSeeds = array();
            foreach ($seeds as $s) {
                $p = wp_parse_url($s);
                if ($p && !empty($p['scheme']) && !empty($p['host'])) {
                    $port = isset($p['port']) ? (':' . $p['port']) : '';
                    $jsonSeeds[] = $p['scheme'] . '://' . $p['host'] . $port . '/wp-json/';
                }
            }
            $seeds = array_values(array_unique(array_merge($seeds, $jsonSeeds)));

            // Allowed hosts from seeds
            $hosts = array(); foreach ($seeds as $s) { $h = parse_url($s, PHP_URL_HOST); if ($h) $hosts[] = $h; }
            $hosts = array_values(array_unique($hosts));

            $upload_dir = wp_upload_dir();
            $job_dir = $upload_dir['basedir'] . '/wgetta/jobs/' . $job_id;
            $log_file = $job_dir . '/stdout.log';

            $crawler = new Wgetta_Crawler();
            $urls = $crawler->discover($seeds, $hosts, 3, 5000, $log_file);

            file_put_contents($job_dir . '/urls.json', json_encode(array_values($urls), JSON_PRETTY_PRINT));

            WP_CLI::success('Dry-run completed. Found ' . count($urls) . ' URLs.');

            if (count($urls) > 0) {
                WP_CLI::line("\nFirst 10 URLs:");
                foreach (array_slice($urls, 0, 10) as $url) { WP_CLI::line('  - ' . $url); }
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

            // Prepare argv and extract seed URLs
            $argv = Wgetta_Job_Runner::wgetta_prepare_argv_or_die($cmd);
            $seeds = array();
            foreach ($argv as $tok) { if (preg_match('#^https?://#i', $tok)) { $seeds[] = $tok; } }
            if (empty($seeds)) { $seeds = array(home_url('/')); }
            // Also seed /wp-json/ per host to traverse REST routes
            $jsonSeeds = array();
            foreach ($seeds as $s) {
                $p = wp_parse_url($s);
                if ($p && !empty($p['scheme']) && !empty($p['host'])) {
                    $port = isset($p['port']) ? (':' . $p['port']) : '';
                    $jsonSeeds[] = $p['scheme'] . '://' . $p['host'] . $port . '/wp-json/';
                }
            }
            $seeds = array_values(array_unique(array_merge($seeds, $jsonSeeds)));

            // Build combined reject pattern from saved patterns
            $patterns = get_option('wgetta_regex_patterns', array());
            if (is_array($patterns)) { $patterns = array_map('trim', $patterns); }
            $combined_pattern = '';
            if (!empty($patterns)) {
                foreach ($patterns as $p) {
                    if ($p === '') { continue; }
                    $v = Wgetta_Job_Runner::wgetta_validate_pattern($p);
                    if (!$v['ok']) { WP_CLI::error('Invalid pattern "' . $p . '": ' . $v['error']); }
                }
                $combined_pattern = '(' . implode(')|(', $patterns) . ')';
                WP_CLI::line('Applied regex exclusions: ' . $combined_pattern);
            }

            // Create job
            $runner = new Wgetta_Job_Runner();
            $job_id = $runner->create_job('execute');
            WP_CLI::line('Job ID: ' . $job_id);

            // Mirror recursively using mirrorer
            $upload_dir = wp_upload_dir();
            $job_dir = $upload_dir['basedir'] . '/wgetta/jobs/' . $job_id;
            $log_file = $job_dir . '/stdout.log';
            $mirrorer = new Wgetta_Mirrorer();
            $mirrorer->mirror($seeds, $job_dir, true, $log_file, $combined_pattern);

            // Manifest
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
            
            // Mark running
            $runner->set_metadata(array('status' => 'running', 'started' => time()));

            // Extract seed URLs and reject pattern from argv
            $seeds = array();
            $reject = '';
            foreach ((array) $argv as $tok) {
                if (preg_match('#^https?://#i', $tok)) { $seeds[] = $tok; continue; }
                if (strpos($tok, '--reject-regex=') === 0) { $reject = substr($tok, strlen('--reject-regex=')); }
            }
            if (empty($seeds)) { $seeds = array(home_url('/')); }

            // Mirror recursively
            $upload_dir = wp_upload_dir();
            $job_dir = $upload_dir['basedir'] . '/wgetta/jobs/' . $job_id;
            $log_file = $job_dir . '/stdout.log';
            $mirrorer = new Wgetta_Mirrorer();
            $mirrorer->mirror($seeds, $job_dir, true, $log_file, $reject);

            // Generate manifest and archive
            $runner->generate_manifest();
            $runner->create_archive_zip('archive.zip');

            // Mark completed
            $status = $runner->get_status();
            if (!is_array($status)) { $status = array(); }
            $status['status'] = 'completed';
            $status['completed'] = time();
            $runner->set_metadata($status);
            
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

            // Legacy argv/flag stripping not required for mirrorer execution

            // Build set of included URLs
            $targets = array();
            foreach ($urls as $url) {
                if (strpos($url, ' #SKIP') !== false) { continue; }
                $clean = trim($url);
                if ($clean !== '') { $targets[$clean] = true; }
            }

            // Mark running status
            $runner->set_metadata(array('status' => 'running', 'started' => time()));

            // Mirror using static-mirror style wget invocation into the job directory
            $upload_dir = wp_upload_dir();
            $job_dir = $upload_dir['basedir'] . '/wgetta/jobs/' . $job_id;
            $log_file = $job_dir . '/stdout.log';
            $mirrorer = new Wgetta_Mirrorer();
            $mirrorer->mirror(array_keys($targets), $job_dir, false, $log_file);

            // Manifest and archive
            $runner->generate_manifest();
            $runner->create_archive_zip('archive.zip');

            // Completed status
            $status = $runner->get_status();
            if (!is_array($status)) { $status = array(); }
            $status['status'] = 'completed';
            $status['completed'] = time();
            $runner->set_metadata($status);
        } catch (Exception $e) {
            error_log('Wgetta plan job failed: ' . $e->getMessage());
        }
    }
}