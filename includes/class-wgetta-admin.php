<?php
/**
 * Admin functionality class
 */
class Wgetta_Admin {
    
    private $plugin_name;
    private $version;
    
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_menu_page(
            'Wgetta',
            'Wgetta',
            'manage_options',
            'wgetta',
            array($this, 'display_settings_page'),
            'dashicons-download',
            30
        );
        
        add_submenu_page(
            'wgetta',
            'Commands',
            'Commands',
            'manage_options',
            'wgetta',
            array($this, 'display_settings_page')
        );
        
        add_submenu_page(
            'wgetta',
            'Plan',
            'Plan',
            'manage_options',
            'wgetta-plan',
            array($this, 'display_plan_page')
        );
        
        add_submenu_page(
            'wgetta',
            'Copy',
            'Copy',
            'manage_options',
            'wgetta-copy',
            array($this, 'display_copy_page')
        );

        add_submenu_page(
            'wgetta',
            'Plan Copy',
            'Plan Copy',
            'manage_options',
            'wgetta-plan-copy',
            array($this, 'display_plan_copy_page')
        );
    }
    
    /**
     * Enqueue admin styles
     */
    public function enqueue_styles($hook) {
        if (strpos($hook, 'wgetta') === false) {
            return;
        }
        
        wp_enqueue_style(
            $this->plugin_name,
            WGETTA_PLUGIN_URL . 'admin/css/wgetta-admin.css',
            array(),
            $this->version,
            'all'
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'wgetta') === false) {
            return;
        }
        
        wp_enqueue_script(
            $this->plugin_name,
            WGETTA_PLUGIN_URL . 'admin/js/wgetta-admin.js',
            array('jquery'),
            $this->version,
            false
        );
        
        // Localize script for AJAX
        wp_localize_script(
            $this->plugin_name,
            'wgetta_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wgetta_ajax_nonce')
            )
        );

        // Load FancyTree assets on Plan Copy page only
        if (isset($_GET['page']) && $_GET['page'] === 'wgetta-plan-copy') {
            wp_enqueue_style(
                'fancytree-css',
                'https://cdn.jsdelivr.net/npm/jquery.fancytree@2/dist/skin-win8/ui.fancytree.min.css',
                array(),
                '2'
            );
            wp_enqueue_script(
                'fancytree-js',
                'https://cdn.jsdelivr.net/npm/jquery.fancytree@2/dist/jquery.fancytree-all-deps.min.js',
                array('jquery'),
                '2',
                true
            );
        }
    }
    
    /**
     * Display settings page
     */
    public function display_settings_page() {
        include_once WGETTA_PLUGIN_DIR . 'admin/partials/wgetta-admin-settings.php';
    }
    
    /**
     * Display plan page
     */
    public function display_plan_page() {
        include_once WGETTA_PLUGIN_DIR . 'admin/partials/wgetta-admin-plan.php';
    }
    
    /**
     * Display copy page
     */
    public function display_copy_page() {
        include_once WGETTA_PLUGIN_DIR . 'admin/partials/wgetta-admin-copy.php';
    }
    
    public function display_plan_copy_page() {
        include_once WGETTA_PLUGIN_DIR . 'admin/partials/wgetta-admin-plan-copy.php';
    }
    
    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $response = array('success' => false, 'message' => '');
        
        // Get and validate command
        $cmd = isset($_POST['wgetta_cmd']) ? stripslashes($_POST['wgetta_cmd']) : '';
        
        try {
            // Validate command
            require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-job-runner.php';
            Wgetta_Job_Runner::wgetta_prepare_argv_or_die($cmd);
            
            // Save command
            update_option('wgetta_cmd', $cmd);
            
            $response['success'] = true;
            $response['message'] = 'Command saved successfully';
        } catch (Exception $e) {
            $response['message'] = 'Invalid command: ' . $e->getMessage();
        }
        
        wp_send_json($response);
    }
    
    /**
     * AJAX handler for enqueueing dry run
     */
    public function ajax_dry_run() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $response = array('success' => false, 'message' => '', 'job_id' => '');
        
        try {
            // Get saved command
            $cmd = get_option('wgetta_cmd', '');
            if (empty($cmd)) {
                throw new RuntimeException('No command configured');
            }
            
            // Prepare argv
            require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-job-runner.php';
            $argv = Wgetta_Job_Runner::wgetta_prepare_argv_or_die($cmd);
            
            // Make dry-run argv
            $argv = Wgetta_Job_Runner::wgetta_make_dryrun_argv($argv);
            
            // Create and run job
            $runner = new Wgetta_Job_Runner();
            $job_id = $runner->create_job('dry-run');
            $runner->run($argv);
            
            // Extract URLs
            $urls = $runner->extract_urls();
            
            $response['success'] = true;
            $response['job_id'] = $job_id;
            $response['message'] = 'Dry run completed';
            $response['data'] = array('urls' => $urls);
            
        } catch (Exception $e) {
            $response['message'] = 'Dry run failed: ' . $e->getMessage();
        }
        
        wp_send_json($response);
    }
    
    /**
     * AJAX handler for regex testing with POSIX ERE
     */
    public function ajax_test_regex() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $response = array('success' => false, 'included' => array(), 'excluded' => array());
        
        try {
            $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
            $patterns = isset($_POST['patterns']) ? wp_unslash($_POST['patterns']) : array();
            if (is_array($patterns)) {
                $patterns = array_map('trim', $patterns);
            }
            
            if (empty($job_id)) {
                throw new RuntimeException('No job ID provided');
            }
            
            // Load URLs from job
            require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-job-runner.php';
            $runner = new Wgetta_Job_Runner();
            $runner->set_job($job_id);
            
            $upload_dir = wp_upload_dir();
            $urls_file = $upload_dir['basedir'] . '/wgetta/jobs/' . $job_id . '/urls.json';
            
            if (!file_exists($urls_file)) {
                throw new RuntimeException('URLs not found for job');
            }
            
            $urls = json_decode(file_get_contents($urls_file), true);
            
            // Validate patterns first
            foreach ($patterns as $p) {
                if ($p === '') { continue; }
                $v = Wgetta_Job_Runner::wgetta_validate_pattern($p);
                if (!$v['ok']) {
                    throw new RuntimeException('Invalid pattern "' . $p . '": ' . $v['error']);
                }
            }

            // Test each URL against patterns (OR semantics)
            $included = array();
            $excluded = array();
            
            foreach ($urls as $url) {
                $matched = false;
                foreach ($patterns as $pattern) {
                    if ($pattern !== '' && Wgetta_Job_Runner::wgetta_posix_match($pattern, $url)) {
                        $matched = true;
                        break;
                    }
                }
                
                if ($matched) {
                    $excluded[] = $url;
                } else {
                    $included[] = $url;
                }
            }
            
            // Save patterns exactly as entered
            update_option('wgetta_regex_patterns', $patterns);
            
            $response['success'] = true;
            $response['included'] = $included;
            $response['excluded'] = $excluded;
            
        } catch (Exception $e) {
            $response['message'] = 'Regex test failed: ' . $e->getMessage();
        }
        
        wp_send_json($response);
    }
    
    /**
     * AJAX handler for saving regex patterns
     */
    public function ajax_save_regex() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $response = array('success' => false, 'message' => '');
        
        try {
            $patterns = isset($_POST['patterns']) ? wp_unslash($_POST['patterns']) : array();
            if (is_array($patterns)) {
                $patterns = array_map('trim', $patterns);
            }
            // Validate before saving
            foreach ($patterns as $p) {
                if ($p === '') { continue; }
                $v = Wgetta_Job_Runner::wgetta_validate_pattern($p);
                if (!$v['ok']) {
                    throw new RuntimeException('Invalid pattern "' . $p . '": ' . $v['error']);
                }
            }
            
            // Clean and validate patterns
            $clean_patterns = array();
            foreach ($patterns as $pattern) {
                $pattern = trim($pattern);
                if (!empty($pattern)) {
                    $clean_patterns[] = $pattern;
                }
            }
            
            // Save patterns as provided
            update_option('wgetta_regex_patterns', $clean_patterns);
            
            $response['success'] = true;
            $response['message'] = 'Regex patterns saved successfully';
            
        } catch (Exception $e) {
            $response['message'] = 'Failed to save patterns: ' . $e->getMessage();
        }
        
        wp_send_json($response);
    }
    
    /**
     * AJAX handler for enqueueing execution
     */
    public function ajax_execute() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $response = array('success' => false, 'message' => '', 'job_id' => '');
        
        try {
            // Get saved command
            $cmd = get_option('wgetta_cmd', '');
            if (empty($cmd)) {
                throw new RuntimeException('No command configured');
            }
            
            // Prepare argv (no --spider injection for execution)
            require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-job-runner.php';
            $argv = Wgetta_Job_Runner::wgetta_prepare_argv_or_die($cmd);
            
            // Apply saved regex patterns
            $patterns = get_option('wgetta_regex_patterns', array());
            if (is_array($patterns)) {
                // Options API stores as-is; do not unslash here. Just trim.
                $patterns = array_map('trim', $patterns);
            }
            if (!empty($patterns)) {
                // Validate patterns; if any invalid, stop with message
                foreach ($patterns as $p) {
                    if ($p === '') { continue; }
                    $v = Wgetta_Job_Runner::wgetta_validate_pattern($p);
                    if (!$v['ok']) {
                        throw new RuntimeException('Invalid pattern "' . $p . '": ' . $v['error']);
                    }
                }
                // Build combined POSIX ERE pattern
                $combined_pattern = '(' . implode(')|(', $patterns) . ')';
                $argv[] = '--reject-regex=' . $combined_pattern;
            }
            
            // Create job
            $runner = new Wgetta_Job_Runner();
            $job_id = $runner->create_job('execute');
            
            $response['success'] = true;
            $response['job_id'] = $job_id;
            $response['message'] = 'Job queued successfully';
            
            // Check for existing scheduled job to prevent duplicates
            if (!wp_next_scheduled('wgetta_execute_job', array($job_id, $argv))) {
                // Schedule immediate background execution
                wp_schedule_single_event(time(), 'wgetta_execute_job', array($job_id, $argv));
                
                // Spawn cron immediately for better reliability
                spawn_cron();
            }
            
        } catch (Exception $e) {
            $response['message'] = 'Execution failed: ' . $e->getMessage();
        }
        
        wp_send_json($response);
    }

    /**
     * Generate plan from dry run and current exclusions
     */
    public function ajax_plan_generate() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        $resp = array('success' => false);
        try {
            $cmd = get_option('wgetta_cmd', '');
            if (empty($cmd)) { throw new RuntimeException('No command configured'); }
            require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-job-runner.php';
            $argv = Wgetta_Job_Runner::wgetta_make_dryrun_argv(Wgetta_Job_Runner::wgetta_prepare_argv_or_die($cmd));
            $runner = new Wgetta_Job_Runner();
            $job_id = $runner->create_job('plan');
            $runner->run($argv);
            $urls = $runner->extract_urls();
            // Apply saved patterns (server-side OR semantics)
            $patterns = get_option('wgetta_regex_patterns', array());
            if (is_array($patterns)) { $patterns = array_map('trim', $patterns); }
            $filtered = array();
            foreach ($urls as $u) {
                $match = false;
                foreach ($patterns as $p) {
                    if ($p !== '' && Wgetta_Job_Runner::wgetta_posix_match($p, $u)) { $match = true; break; }
                }
                if (!$match) { $filtered[] = $u; }
            }
            // Save plan.csv
            $upload_dir = wp_upload_dir();
            $job_dir = $upload_dir['basedir'] . '/wgetta/jobs/' . $job_id;
            file_put_contents($job_dir . '/plan.csv', implode("\n", $filtered));
            $resp['success'] = true; $resp['job_id'] = $job_id; $resp['urls'] = $filtered;
        } catch (Exception $e) {
            $resp['message'] = $e->getMessage();
        }
        wp_send_json($resp);
    }

    /** Save edited plan */
    public function ajax_plan_save() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        $resp = array('success' => false);
        try {
            $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
            $urls = isset($_POST['urls']) ? array_map('trim', (array) $_POST['urls']) : array();
            if (!$job_id) { throw new RuntimeException('Missing job'); }
            $upload_dir = wp_upload_dir();
            $job_dir = $upload_dir['basedir'] . '/wgetta/jobs/' . $job_id;
            if (!is_dir($job_dir)) { throw new RuntimeException('Job not found'); }
            // Persist planned URLs as provided (use trailing #SKIP marker to denote unchecked items)
            file_put_contents($job_dir . '/plan.csv', implode("\n", array_filter($urls)));
            $resp['success'] = true; $resp['message'] = 'Plan saved';
        } catch (Exception $e) {
            $resp['message'] = $e->getMessage();
        }
        wp_send_json($resp);
    }

    /** Execute plan */
    public function ajax_plan_execute() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        $resp = array('success' => false);
        try {
            $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
            if (!$job_id) {
                // If no job, create a new plan job using current plan name (optional)
                $job_id = $this->create_empty_plan_job();
            }
            // Schedule background plan job
            if (!wp_next_scheduled('wgetta_execute_plan_job', array($job_id))) {
                wp_schedule_single_event(time(), 'wgetta_execute_plan_job', array($job_id));
            }
            $resp['success'] = true; $resp['job_id'] = $job_id;
        } catch (Exception $e) {
            $resp['message'] = $e->getMessage();
        }
        wp_send_json($resp);
    }

    /** Create an empty plan job dir (used when executing a loaded named plan) */
    private function create_empty_plan_job() {
        require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-job-runner.php';
        $runner = new Wgetta_Job_Runner();
        return $runner->create_job('plan');
    }

    /** Create plan job from named plan */
    public function ajax_plan_create() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        $resp = array('success' => false);
        try {
            $name = isset($_POST['name']) ? sanitize_file_name($_POST['name']) : '';
            if ($name === '') { throw new RuntimeException('Missing plan name'); }
            $upload_dir = wp_upload_dir();
            $plan_file = trailingslashit($upload_dir['basedir']) . 'wgetta/plans/' . $name . '.csv';
            if (!file_exists($plan_file)) { throw new RuntimeException('Plan not found'); }
            // Create a new plan job and copy plan.csv into it
            require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-job-runner.php';
            $runner = new Wgetta_Job_Runner();
            $job_id = $runner->create_job('plan');
            $job_dir = trailingslashit($upload_dir['basedir']) . 'wgetta/jobs/' . $job_id;
            copy($plan_file, $job_dir . '/plan.csv');
            $resp['success'] = true; $resp['job_id'] = $job_id;
        } catch (Exception $e) {
            $resp['message'] = $e->getMessage();
        }
        wp_send_json($resp);
    }

    /** List saved named plans */
    public function ajax_plan_list() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        $resp = array('success' => true, 'plans' => array());
        $upload_dir = wp_upload_dir();
        $plan_dir = trailingslashit($upload_dir['basedir']) . 'wgetta/plans';
        if (is_dir($plan_dir)) {
            foreach (glob($plan_dir . '/*.csv') as $file) {
                $resp['plans'][] = array(
                    'name' => basename($file, '.csv'),
                    'modified' => filemtime($file),
                    'count' => count(array_filter(array_map('trim', explode("\n", file_get_contents($file)))))
                );
            }
        }
        wp_send_json($resp);
    }

    /** Load a named plan */
    public function ajax_plan_load() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        $name = isset($_POST['name']) ? sanitize_file_name($_POST['name']) : '';
        $resp = array('success' => false);
        try {
            if ($name === '') { throw new RuntimeException('Missing plan name'); }
            $upload_dir = wp_upload_dir();
            $plan_file = trailingslashit($upload_dir['basedir']) . 'wgetta/plans/' . $name . '.csv';
            if (!file_exists($plan_file)) { throw new RuntimeException('Plan not found'); }
            $urls = array_filter(array_map('trim', explode("\n", file_get_contents($plan_file))));
            $resp['success'] = true; $resp['urls'] = $urls; $resp['name'] = $name;
        } catch (Exception $e) {
            $resp['message'] = $e->getMessage();
        }
        wp_send_json($resp);
    }

    /** Save current plan under a name */
    public function ajax_plan_save_named() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        $name = isset($_POST['name']) ? sanitize_file_name($_POST['name']) : '';
        $urls = isset($_POST['urls']) ? array_map('trim', (array) $_POST['urls']) : array();
        $resp = array('success' => false);
        try {
            if ($name === '') { throw new RuntimeException('Missing plan name'); }
            $upload_dir = wp_upload_dir();
            $plan_dir = trailingslashit($upload_dir['basedir']) . 'wgetta/plans';
            if (!wp_mkdir_p($plan_dir)) { throw new RuntimeException('Failed to create plans directory'); }
            $plan_file = $plan_dir . '/' . $name . '.csv';
            file_put_contents($plan_file, implode("\n", array_filter($urls)));
            $resp['success'] = true; $resp['message'] = 'Plan saved';
        } catch (Exception $e) {
            $resp['message'] = $e->getMessage();
        }
        wp_send_json($resp);
    }
    
    /**
     * AJAX handler for getting log tail
     */
    public function ajax_log_tail() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $response = array('success' => false, 'content' => '', 'offset' => 0, 'status' => null, 'summary' => null);
        
        try {
            $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
            $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
            
            if (empty($job_id)) {
                throw new RuntimeException('No job ID provided');
            }
            
            require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-job-runner.php';
            $runner = new Wgetta_Job_Runner();
            $runner->set_job($job_id);
            
            // Get log tail
            $tail = $runner->get_log_tail($offset);
            
            // Get status
            $status = $runner->get_status();

            // If job finished, compute summary and recent history
            if (is_array($status) && isset($status['status']) && in_array($status['status'], array('completed', 'completed_with_errors', 'failed', 'timeout', 'killed'), true)) {
                $upload_dir = wp_upload_dir();
                $job_dir = $upload_dir['basedir'] . '/wgetta/jobs/' . $job_id;
                $manifest_file = $job_dir . '/manifest.txt';
                $files_downloaded = 0;
                $total_bytes = 0;

                if (file_exists($manifest_file)) {
                    $lines = array_filter(array_map('trim', explode("\n", file_get_contents($manifest_file))));
                    $files_downloaded = count($lines);
                    foreach ($lines as $rel) {
                        $abs = $job_dir . '/' . ltrim($rel, '/');
                        if (is_file($abs)) {
                            $size = filesize($abs);
                            if ($size !== false) {
                                $total_bytes += $size;
                            }
                        }
                    }
                }

                $elapsed = 0;
                if (!empty($status['started']) && !empty($status['completed'])) {
                    $elapsed = max(0, intval($status['completed']) - intval($status['started']));
                }

                $response['summary'] = array(
                    'files' => $files_downloaded,
                    'bytes' => $total_bytes,
                    'elapsed_seconds' => $elapsed,
                    'path' => $job_dir
                );

                // Populate recent executions history (last 5 jobs by mtime)
                $jobs_root = $upload_dir['basedir'] . '/wgetta/jobs';
                $history = array();
                if (is_dir($jobs_root)) {
                    $dirs = array_filter(glob($jobs_root . '/*', GLOB_ONLYDIR), 'is_dir');
                    // Sort by modified time desc
                    usort($dirs, function($a, $b) { return filemtime($b) - filemtime($a); });
                    foreach (array_slice($dirs, 0, 5) as $dir) {
                        $sid = basename($dir);
                        $sstatus = null;
                        $sfile = $dir . '/status.json';
                        if (file_exists($sfile)) {
                            $sstatus = json_decode(file_get_contents($sfile), true);
                        }
                        $manifest = $dir . '/manifest.txt';
                        $count = 0;
                        if (file_exists($manifest)) {
                            $count = count(array_filter(array_map('trim', explode("\n", file_get_contents($manifest)))));
                        }
                        $history[] = array(
                            'id' => $sid,
                            'status' => $sstatus ? ($sstatus['status'] ?? null) : null,
                            'files' => $count,
                            'path' => $dir,
                            'modified' => filemtime($dir)
                        );
                    }
                }
                $response['history'] = $history;
            }
            
            $response['success'] = true;
            $response['content'] = $tail['content'];
            $response['offset'] = $tail['offset'];
            $response['status'] = $status;
            
        } catch (Exception $e) {
            $response['message'] = 'Failed to get log: ' . $e->getMessage();
        }
        
        wp_send_json($response);
    }

    /**
     * AJAX: fetch recent execution history
     */
    public function ajax_history() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $response = array('success' => true, 'history' => array());
        try {
            $upload_dir = wp_upload_dir();
            $jobs_root = $upload_dir['basedir'] . '/wgetta/jobs';
            $history = array();
            if (is_dir($jobs_root)) {
                $dirs = array_filter(glob($jobs_root . '/*', GLOB_ONLYDIR), 'is_dir');
                usort($dirs, function($a, $b) { return filemtime($b) - filemtime($a); });
                foreach (array_slice($dirs, 0, 5) as $dir) {
                    $sid = basename($dir);
                    $sstatus = null;
                    $sfile = $dir . '/status.json';
                    if (file_exists($sfile)) {
                        $sstatus = json_decode(file_get_contents($sfile), true);
                    }
                    $manifest = $dir . '/manifest.txt';
                    $count = 0;
                    if (file_exists($manifest)) {
                        $count = count(array_filter(array_map('trim', explode("\n", file_get_contents($manifest)))));
                    }
                    $history[] = array(
                        'id' => $sid,
                        'status' => $sstatus ? ($sstatus['status'] ?? null) : null,
                        'files' => $count,
                        'path' => $dir,
                        'modified' => filemtime($dir)
                    );
                }
            }
            $response['history'] = $history;
        } catch (Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        }
        wp_send_json($response);
    }
}