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
            'wgetta-plan-copy',
            array($this, 'display_plan_copy_page'),
            'dashicons-download',
            30
        );
        
        add_submenu_page(
            'wgetta-plan-copy',
            'Plan',
            'Plan',
            'manage_options',
            'wgetta-plan-copy',
            array($this, 'display_plan_copy_page')
        );
        
        add_submenu_page(
            'wgetta-plan-copy',
            'Run Plan',
            'Run Plan',
            'manage_options',
            'wgetta-plan-run',
            array($this, 'display_plan_run_page')
        );
        
        add_submenu_page(
            'wgetta-plan-copy',
            'GitLab Deploy',
            'GitLab Deploy',
            'manage_options',
            'wgetta-gitlab-deploy',
            array($this, 'display_git_deploy_page')
        );

        add_submenu_page(
            'wgetta-plan-copy',
            'GitLab Settings',
            'GitLab Settings',
            'manage_options',
            'wgetta-gitlab-settings',
            array($this, 'display_git_settings_page')
        );
    }
    
    /**
     * Enqueue admin styles
     */
    public function enqueue_styles($hook) {
        if (strpos($hook, 'wgetta') === false) {
            return;
        }
        
        $css_ver = @filemtime(WGETTA_PLUGIN_DIR . 'admin/css/wgetta-admin.css');
        if (!$css_ver) { $css_ver = $this->version; }
        wp_enqueue_style(
            $this->plugin_name,
            WGETTA_PLUGIN_URL . 'admin/css/wgetta-admin.css',
            array(),
            $css_ver,
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
        
        $js_ver = @filemtime(WGETTA_PLUGIN_DIR . 'admin/js/wgetta-admin.js');
        if (!$js_ver) { $js_ver = $this->version; }
        wp_enqueue_script(
            $this->plugin_name,
            WGETTA_PLUGIN_URL . 'admin/js/wgetta-admin.js',
            array('jquery'),
            $js_ver,
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
    
    // Legacy settings/plan pages removed

    public function display_plan_copy_page() {
        include_once WGETTA_PLUGIN_DIR . 'admin/partials/wgetta-admin-plan-copy.php';
    }
    
    public function display_plan_run_page() {
        include_once WGETTA_PLUGIN_DIR . 'admin/partials/wgetta-admin-run.php';
    }

    public function display_history_page() {}

    public function display_git_deploy_page() {
        include_once WGETTA_PLUGIN_DIR . 'admin/partials/wgetta-admin-git.php';
    }

    public function display_git_settings_page() {
        include_once WGETTA_PLUGIN_DIR . 'admin/partials/wgetta-admin-git-settings.php';
    }

    /** Save GitLab settings */
    public function ajax_git_save() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        $url = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';
        $project = isset($_POST['project_id']) ? sanitize_text_field($_POST['project_id']) : '';
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $include_meta = isset($_POST['include_meta']) && $_POST['include_meta'] ? 1 : 0;
        $committer_name = isset($_POST['committer_name']) ? sanitize_text_field($_POST['committer_name']) : '';
        $committer_email = isset($_POST['committer_email']) ? sanitize_email($_POST['committer_email']) : '';
        $branch_template = isset($_POST['branch_template']) ? sanitize_text_field($_POST['branch_template']) : '';
        update_option('wgetta_gitlab', array(
            'url' => $url,
            'project_id' => $project,
            'token' => $token,
            'include_meta' => $include_meta,
            'committer_name' => $committer_name,
            'committer_email' => $committer_email,
            'branch_template' => ($branch_template !== '' ? $branch_template : 'wgetta/{plan_name}')
        ));
        wp_send_json(array('success' => true));
    }

    /** Dry-run deploy: just echo what we'd commit */
    public function ajax_git_deploy_dry() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        $resp = array('success' => false);
        try {
            $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
            if (!$job_id) { throw new RuntimeException('Missing job'); }
            $upload_dir = wp_upload_dir();
            $job_dir = trailingslashit($upload_dir['basedir']) . 'wgetta/jobs/' . $job_id;
            if (!is_dir($job_dir)) { throw new RuntimeException('Job not found'); }
            $resp['success'] = true;
            $resp['job_dir'] = $job_dir;
            $resp['output'] = 'Would run: git clone <project> && rsync ' . $job_dir . '/ -> repo && git add -A && git commit && git push';
        } catch (Exception $e) {
            $resp['message'] = $e->getMessage();
        }
        wp_send_json($resp);
    }

    /** Push deploy (scaffold): validate and return success */
    public function ajax_git_deploy_push() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        $resp = array('success' => false);
        try {
            $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
            if ($job_id === '' || strpos($job_id, 'job_') !== 0) { throw new RuntimeException('Missing job'); }
            $upload_dir = wp_upload_dir();
            $job_dir = trailingslashit($upload_dir['basedir']) . 'wgetta/jobs/' . $job_id;
            if (!is_dir($job_dir)) { throw new RuntimeException('Job not found'); }

            // Ensure git is available
            $gitCheck = $this->run_cmd(array('git', '--version'));
            if ($gitCheck['code'] !== 0) { throw new RuntimeException('git not available on server'); }

            // Load GitLab settings
            $settings = get_option('wgetta_gitlab', array('url' => '', 'project_id' => '', 'token' => ''));
            $baseUrl = isset($settings['url']) ? rtrim($settings['url'], '/') : '';
            $projectId = isset($settings['project_id']) ? $settings['project_id'] : '';
            $token = isset($settings['token']) ? $settings['token'] : '';
            if ($baseUrl === '' || $projectId === '' || $token === '') { throw new RuntimeException('GitLab settings incomplete'); }

            // Fetch project to get repo URL and default branch
            $api = $baseUrl . '/api/v4/projects/' . rawurlencode($projectId);
            $res = wp_remote_get($api, array('headers' => array('PRIVATE-TOKEN' => $token), 'timeout' => 15));
            if (is_wp_error($res)) { throw new RuntimeException($res->get_error_message()); }
            $code = wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) { throw new RuntimeException('GitLab API HTTP ' . $code); }
            $proj = json_decode(wp_remote_retrieve_body($res), true);
            $http_repo = isset($proj['http_url_to_repo']) ? $proj['http_url_to_repo'] : '';
            $default_branch = isset($proj['default_branch']) ? $proj['default_branch'] : 'main';
            if ($http_repo === '') { throw new RuntimeException('Project repo URL missing'); }

            // Build credentialed remote URL (mask token in logs later)
            $parts = wp_parse_url($http_repo);
            if (!$parts || !isset($parts['scheme']) || !isset($parts['host'])) { throw new RuntimeException('Invalid repo URL'); }
            $cred = 'oauth2:' . rawurlencode($token) . '@';
            $remote = $parts['scheme'] . '://' . $cred . $parts['host'] . (isset($parts['port']) ? (':' . $parts['port']) : '') . (isset($parts['path']) ? $parts['path'] : '');

            // Create temp workdir under uploads to avoid perms issues
            $work_root = trailingslashit($upload_dir['basedir']) . 'wgetta/tmp';
            if (!wp_mkdir_p($work_root)) { throw new RuntimeException('Failed to create tmp directory'); }
            $repo_dir = $work_root . '/repo_' . uniqid();
            if (!wp_mkdir_p($repo_dir)) { throw new RuntimeException('Failed to prepare repo directory'); }

            $log = array();
            $mask = $token;

            // Clone shallow
            $res1 = $this->run_cmd(array('git', 'clone', '--depth', '1', $remote, $repo_dir));
            $log[] = $res1['out'];
            if ($res1['code'] !== 0) { throw new RuntimeException('git clone failed'); }

            // Ensure committer identity (local repo only)
            $site_host = parse_url(home_url('/'), PHP_URL_HOST);
            if (!is_string($site_host) || $site_host === '') { $site_host = 'localhost'; }
            // Use configured committer if provided
            $author_name = isset($settings['committer_name']) && $settings['committer_name'] !== '' ? $settings['committer_name'] : get_bloginfo('name');
            if (!is_string($author_name) || $author_name === '') { $author_name = 'Wgetta'; }
            $author_email = isset($settings['committer_email']) && is_email($settings['committer_email']) ? $settings['committer_email'] : ('noreply@' . $site_host);
            $cfg1 = $this->run_cmd(array('git', '-C', $repo_dir, 'config', 'user.name', $author_name));
            $cfg2 = $this->run_cmd(array('git', '-C', $repo_dir, 'config', 'user.email', $author_email));
            $log[] = $cfg1['out'];
            $log[] = $cfg2['out'];

            // Decide which files to include: only crawled files by default (from manifest)
            // Optionally include metadata if toggle is set in settings (hidden setting)
            require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-job-runner.php';
            $runner = new Wgetta_Job_Runner();
            $runner->set_job($job_id);
            $manifest_files = $runner->generate_manifest();
            // Exclude known archives
            $manifest_files = array_values(array_filter($manifest_files, function($rel){
                return $rel !== 'archive.zip' && $rel !== 'plan.csv';
            }));

            $include_meta = !empty($settings['include_meta']);
            $meta_files = array();
            if ($include_meta) {
                foreach (array('manifest.txt', 'status.json', 'urls.json', 'command.txt', 'plan.csv') as $m) {
                    if (file_exists($job_dir . '/' . $m)) { $meta_files[] = $m; }
                }
            }
            $files_to_copy = array_merge($manifest_files, $meta_files);
            foreach ($files_to_copy as $rel) {
                $src = $job_dir . '/' . ltrim($rel, '/');
                $dst = $repo_dir . '/' . ltrim($rel, '/');
                $dstDir = dirname($dst);
                if (!is_dir($dstDir)) { wp_mkdir_p($dstDir); }
                if (is_file($src)) { copy($src, $dst); }
            }

            // git add/commit
            $res2 = $this->run_cmd(array('git', '-C', $repo_dir, 'add', '-A'));
            $log[] = $res2['out'];
            // Commit; if nothing to commit, this exits non-zero; detect message
            // Determine branch name from plan_name if present
            $status = $runner->get_status();
            $plan_name = is_array($status) && !empty($status['plan_name']) ? $status['plan_name'] : '';
            $branch_slug = $plan_name ? sanitize_title($plan_name) : $job_id;
            $message = 'Wgetta deploy ' . ($plan_name ?: $job_id) . ' on ' . date('c');
            $res3 = $this->run_cmd(array('git', '-C', $repo_dir, 'commit', '-m', $message, '--author=Wp Wgetta <noreply@example.com>'));
            $log[] = $res3['out'];
            $nothingToCommit = (strpos($res3['out'], 'nothing to commit') !== false);

            // Create/force branch using template from settings
            $template = isset($settings['branch_template']) && $settings['branch_template'] !== '' ? $settings['branch_template'] : 'wgetta/{plan_name}';
            $date_str = date('Ymd-His');
            $replacements = array(
                '{plan_name}' => ($plan_name ?: $job_id),
                '{job_id}' => $job_id,
                '{date}' => $date_str
            );
            $branch_name = strtr($template, $replacements);
            // sanitize to a safe git ref (basic)
            $branch_name = preg_replace('/[^A-Za-z0-9._\-\/]+/', '-', $branch_name);
            $branch_name = trim($branch_name, '-/');
            if ($branch_name === '') { $branch_name = 'wgetta-' . $date_str; }
            $branch = $branch_name;
            $this->run_cmd(array('git', '-C', $repo_dir, 'checkout', '-B', $branch));
            $res4 = $this->run_cmd(array('git', '-C', $repo_dir, 'push', 'origin', $branch));
            $log[] = $res4['out'];
            if ($res4['code'] !== 0) { throw new RuntimeException('git push failed'); }

            // Clean up
            $this->rrmdir($repo_dir);

            $full = implode("\n", $log);
            if ($mask) { $full = str_replace($mask, '***', $full); }
            $resp['success'] = true;
            $resp['output'] = $full . ($nothingToCommit ? "\n(nothing to commit)" : '');
        } catch (Exception $e) {
            $resp['message'] = $e->getMessage();
        }
        wp_send_json($resp);
    }

    private function run_cmd($argv) {
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );
        $env = array(
            'LC_ALL' => 'C',
            'PATH' => '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin'
        );
        $proc = proc_open($argv, $descriptors, $pipes, null, $env);
        if (!is_resource($proc)) { return array('code' => 1, 'out' => 'Failed to start process'); }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $code = proc_close($proc);
        return array('code' => $code, 'out' => trim($stdout . (strlen($stderr) ? "\n" . $stderr : '')));
    }

    

    /** Test GitLab connection */
    public function ajax_git_test() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        $resp = array('success' => false);
        try {
            $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
            $project = isset($_POST['project_id']) ? sanitize_text_field($_POST['project_id']) : '';
            $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
            if (!$url || !$project || !$token) { throw new RuntimeException('Missing settings'); }
            $api = rtrim($url, '/') . '/api/v4/projects/' . rawurlencode($project);
            $res = wp_remote_get($api, array('headers' => array('PRIVATE-TOKEN' => $token), 'timeout' => 10));
            if (is_wp_error($res)) { throw new RuntimeException($res->get_error_message()); }
            $code = wp_remote_retrieve_response_code($res);
            if ($code >= 200 && $code < 300) {
                $body = json_decode(wp_remote_retrieve_body($res), true);
                $resp['success'] = true;
                $resp['project'] = array(
                    'name' => $body['name'] ?? '',
                    'path_with_namespace' => $body['path_with_namespace'] ?? '',
                    'default_branch' => $body['default_branch'] ?? ''
                );
            } else {
                throw new RuntimeException('HTTP ' . $code . ' from GitLab');
            }
        } catch (Exception $e) {
            $resp['message'] = $e->getMessage();
        }
        wp_send_json($resp);
    }

    /** Set job metadata (e.g., plan_name) */
    public function ajax_set_job_meta() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        $resp = array('success' => false);
        try {
            $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
            $meta = isset($_POST['meta']) ? (array) $_POST['meta'] : array();
            if (!$job_id) { throw new RuntimeException('Missing job'); }
            require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-job-runner.php';
            $runner = new Wgetta_Job_Runner();
            $runner->set_job($job_id);
            $runner->set_metadata($meta);
            $resp['success'] = true;
        } catch (Exception $e) {
            $resp['message'] = $e->getMessage();
        }
        wp_send_json($resp);
    }

    /** Remove a job directory (admin only) */
    public function ajax_job_remove() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        $resp = array('success' => false);
        try {
            $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
            if ($job_id === '' || strpos($job_id, 'job_') !== 0) { throw new RuntimeException('Invalid job'); }
            $upload_dir = wp_upload_dir();
            $jobs_root = trailingslashit($upload_dir['basedir']) . 'wgetta/jobs';
            $job_dir = realpath($jobs_root . '/' . $job_id);
            $root_real = realpath($jobs_root);
            if ($job_dir === false || $root_real === false || strpos($job_dir, $root_real) !== 0) { throw new RuntimeException('Job not found'); }
            // Recursively delete directory
            $this->rrmdir($job_dir);
            $resp['success'] = true;
        } catch (Exception $e) {
            $resp['message'] = $e->getMessage();
        }
        wp_send_json($resp);
    }

    private function rrmdir($dir) {
        if (!is_dir($dir)) { return; }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') { continue; }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
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
    
    /** Dry run via PHP crawler */
    public function ajax_dry_run() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        $response = array('success' => false, 'message' => '', 'job_id' => '');
        try {
            $cmd = get_option('wgetta_cmd', '');
            if ($cmd === '') { throw new RuntimeException('No command configured'); }
            require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-job-runner.php';
            $argv = Wgetta_Job_Runner::wgetta_prepare_argv_or_die($cmd);
            $seeds = array(); foreach ($argv as $tok) { if (preg_match('#^https?://#i', $tok)) { $seeds[] = $tok; } }
            if (empty($seeds)) { $seeds = array(home_url('/')); }
            // Also seed /wp-json/ per host
            $jsonSeeds = array();
            foreach ($seeds as $s) {
                $p = wp_parse_url($s);
                if ($p && !empty($p['scheme']) && !empty($p['host'])) {
                    $port = isset($p['port']) ? (':' . $p['port']) : '';
                    $jsonSeeds[] = $p['scheme'] . '://' . $p['host'] . $port . '/wp-json/';
                }
            }
            $seeds = array_values(array_unique(array_merge($seeds, $jsonSeeds)));
            $hosts = array(); foreach ($seeds as $s) { $h = parse_url($s, PHP_URL_HOST); if ($h) $hosts[] = $h; }
            $hosts = array_values(array_unique($hosts));
            $runner = new Wgetta_Job_Runner();
            $job_id = $runner->create_job('dry-run');
            $upload_dir = wp_upload_dir();
            $job_dir = $upload_dir['basedir'] . '/wgetta/jobs/' . $job_id;
            $log_file = $job_dir . '/stdout.log';
            require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-crawler.php';
            $crawler = new Wgetta_Crawler();
            // Increase depth to better approximate wget --spider discovery
            $urls = $crawler->discover($seeds, $hosts, 3, 5000, $log_file);
            file_put_contents($job_dir . '/urls.json', json_encode($urls, JSON_PRETTY_PRINT));
            $response['success'] = true; $response['job_id'] = $job_id; $response['message'] = 'Dry run completed'; $response['data'] = array('urls' => $urls);
        } catch (Exception $e) {
            $response['message'] = 'Dry run failed: ' . $e->getMessage();
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
            $argv = Wgetta_Job_Runner::wgetta_prepare_argv_or_die($cmd);
            $seeds = array();
            foreach ($argv as $tok) { if (preg_match('#^https?://#i', $tok)) { $seeds[] = $tok; } }
            if (empty($seeds)) { $seeds = array(home_url('/')); }
            // Also seed /wp-json/ per host
            $jsonSeeds = array();
            foreach ($seeds as $s) {
                $p = wp_parse_url($s);
                if ($p && !empty($p['scheme']) && !empty($p['host'])) {
                    $port = isset($p['port']) ? (':' . $p['port']) : '';
                    $jsonSeeds[] = $p['scheme'] . '://' . $p['host'] . $port . '/wp-json/';
                }
            }
            $seeds = array_values(array_unique(array_merge($seeds, $jsonSeeds)));
            $hosts = array(); foreach ($seeds as $s) { $h = parse_url($s, PHP_URL_HOST); if ($h) { $hosts[] = $h; } }
            $hosts = array_values(array_unique($hosts));

            $runner = new Wgetta_Job_Runner();
            $job_id = $runner->create_job('plan');
            $upload_dir = wp_upload_dir();
            $job_dir = $upload_dir['basedir'] . '/wgetta/jobs/' . $job_id;
            $log_file = $job_dir . '/stdout.log';

            require_once WGETTA_PLUGIN_DIR . 'includes/class-wgetta-crawler.php';
            $crawler = new Wgetta_Crawler();
            $urls = $crawler->discover($seeds, $hosts, 1, 2000, $log_file);

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
                $lines = array_filter(array_map('trim', explode("\n", file_get_contents($file))));
                $included = 0; $total = 0;
                foreach ($lines as $ln) { $total++; if (strpos($ln, ' #SKIP') === false) { $included++; } }
                $resp['plans'][] = array(
                    'name' => basename($file, '.csv'),
                    'modified' => filemtime($file),
                    'included' => $included,
                    'total' => $total
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

    /** Delete a named plan */
    public function ajax_plan_delete() {
        check_ajax_referer('wgetta_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        $name = isset($_POST['name']) ? sanitize_file_name($_POST['name']) : '';
        $resp = array('success' => false);
        try {
            if ($name === '') { throw new RuntimeException('Missing plan name'); }
            $upload_dir = wp_upload_dir();
            $plan_dir = trailingslashit($upload_dir['basedir']) . 'wgetta/plans';
            $plan_file = $plan_dir . '/' . $name . '.csv';
            if (!file_exists($plan_file)) { throw new RuntimeException('Plan not found'); }
            if (!is_dir($plan_dir)) { throw new RuntimeException('Plans directory missing'); }
            // Ensure we only delete within the plans directory
            $real_dir = realpath($plan_dir);
            $real_file = realpath($plan_file);
            if ($real_dir === false || $real_file === false || strpos($real_file, $real_dir) !== 0) { throw new RuntimeException('Invalid path'); }
            if (!@unlink($real_file)) { throw new RuntimeException('Failed to delete plan'); }
            $resp['success'] = true;
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

                $zip_url = null;
                if (!empty($upload_dir['baseurl'])) {
                    $zip_url = trailingslashit($upload_dir['baseurl']) . 'wgetta/jobs/' . $job_id . '/archive.zip';
                }
                $response['summary'] = array(
                    'files' => $files_downloaded,
                    'bytes' => $total_bytes,
                    'elapsed_seconds' => $elapsed,
                    'path' => $job_dir,
                    'zip_url' => (file_exists($job_dir . '/archive.zip') ? $zip_url : null)
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
                        $zip_url = null;
                        if (file_exists($dir . '/archive.zip') && !empty($upload_dir['baseurl'])) {
                            $zip_url = trailingslashit($upload_dir['baseurl']) . 'wgetta/jobs/' . $sid . '/archive.zip';
                        }
                        $history[] = array(
                            'id' => $sid,
                            'status' => $sstatus ? ($sstatus['status'] ?? null) : null,
                            'files' => $count,
                            'path' => $dir,
                            'zip_url' => $zip_url,
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
                        'modified' => filemtime($dir),
                        'plan_name' => $sstatus ? ($sstatus['plan_name'] ?? null) : null,
                        'urls_included' => $sstatus ? ($sstatus['urls_included'] ?? null) : null,
                        'urls_total' => $sstatus ? ($sstatus['urls_total'] ?? null) : null
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