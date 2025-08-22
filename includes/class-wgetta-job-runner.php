<?php
/**
 * Job Runner - Safe command execution with proc_open
 */
class Wgetta_Job_Runner {
    
    private $job_id;
    private $job_dir;
    private $max_runtime = 300; // 5 minutes default
    private $max_log_size = 10485760; // 10MB default
    
    /**
     * Parse shell command string into argv array
     */
    public static function wgetta_shellwords($cmd) {
        $cmd = trim($cmd);
        if (empty($cmd)) {
            return array();
        }
        
        $argv = array();
        $current = '';
        $in_single = false;
        $in_double = false;
        $escaped = false;
        
        for ($i = 0; $i < strlen($cmd); $i++) {
            $char = $cmd[$i];
            
            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }
            
            if ($char === '\\') {
                if ($in_single) {
                    $current .= $char;
                } else {
                    $escaped = true;
                }
                continue;
            }
            
            if ($char === "'" && !$in_double) {
                $in_single = !$in_single;
                continue;
            }
            
            if ($char === '"' && !$in_single) {
                $in_double = !$in_double;
                continue;
            }
            
            if ($char === ' ' && !$in_single && !$in_double) {
                if ($current !== '') {
                    $argv[] = $current;
                    $current = '';
                }
                continue;
            }
            
            $current .= $char;
        }
        
        if ($current !== '') {
            $argv[] = $current;
        }
        
        return $argv;
    }
    
    /**
     * Allowed wget options for security
     */
    private static $allowed_options = array(
        '--spider', '--recursive', '--level', '--no-parent', '--page-requisites',
        '--convert-links', '--restrict-file-names', '--no-clobber', '--timeout',
        '--tries', '--wait', '--random-wait', '--user-agent', '--reject',
        '--accept', '--domains', '--exclude-domains', '--reject-regex',
        '--accept-regex', '--no-verbose', '-nv', '--quiet', '-q',
        '--server-response', '--timestamping', '-N', '--backup-converted',
        '--mirror', '-m', '--span-hosts', '-H', '--relative', '-L', '--inet4-only',
        '--no-host-directories', '--protocol-directories', '--cut-dirs',
        '--http-user', '--http-password', '--no-check-certificate',
        '--ca-certificate', '--ca-directory', '--random-file', '--egd-file'
    );
    
    /**
     * Dangerous options that should be rejected
     */
    private static $dangerous_options = array(
        '--directory-prefix', '-P', '--output-file', '-o', '--append-output', '-a',
        '--input-file', '-i', '--force-directories', '--no-directories', 
        '--execute', '-e', '--config', '--output-document', '-O'
    );
    
    /**
     * Prepare argv or throw exception with whitelist validation
     */
    public static function wgetta_prepare_argv_or_die($cmd) {
        $argv = self::wgetta_shellwords($cmd);
        
        if (empty($argv)) {
            throw new RuntimeException('Empty command.');
        }
        
        if (basename($argv[0]) !== 'wget') {
            throw new RuntimeException('Only wget is permitted.');
        }
        
        // Validate options
        for ($i = 1; $i < count($argv); $i++) {
            $arg = $argv[$i];
            
            // Skip URLs and non-option arguments
            if (!preg_match('/^-/', $arg)) {
                continue;
            }
            
            // Check for dangerous options
            foreach (self::$dangerous_options as $dangerous) {
                if ($arg === $dangerous || preg_match('/^' . preg_quote($dangerous, '/') . '=/', $arg)) {
                    throw new RuntimeException("Option '$dangerous' is not allowed for security reasons.");
                }
            }
            
            // Extract option name for whitelist check
            $option_name = $arg;
            if (preg_match('/^([^=]+)=/', $arg, $matches)) {
                $option_name = $matches[1];
            }
            
            // Check whitelist
            if (!in_array($option_name, self::$allowed_options)) {
                throw new RuntimeException("Option '$option_name' is not in the allowed list.");
            }
        }
        
        return $argv;
    }
    
    /**
     * Make dry-run argv by injecting --spider
     */
    public static function wgetta_make_dryrun_argv($argv) {
        // Check if --spider already present
        foreach ($argv as $arg) {
            if ($arg === '--spider') {
                return $argv;
            }
        }
        
        // Inject --spider after binary (position 1)
        array_splice($argv, 1, 0, '--spider');
        return $argv;
    }
    
    /**
     * Create a new job
     */
    public function create_job($type = 'execute') {
        $this->job_id = uniqid('job_');
        
        // Create job directory
        $upload_dir = wp_upload_dir();
        $this->job_dir = $upload_dir['basedir'] . '/wgetta/jobs/' . $this->job_id;
        
        if (!wp_mkdir_p($this->job_dir)) {
            throw new RuntimeException('Failed to create job directory');
        }
        
        // Initialize status file
        $this->update_status(array(
            'id' => $this->job_id,
            'type' => $type,
            'status' => 'queued',
            'created' => time(),
            'started' => null,
            'completed' => null,
            'pid' => null
        ));
        
        return $this->job_id;
    }
    
    /**
     * Run wget command
     */
    public function run($argv) {
        // Update status
        $status = $this->get_status();
        $status['status'] = 'running';
        $status['started'] = time();
        $this->update_status($status);
        
        // Enforce download directory - add --directory-prefix to job dir
        $argv_with_dir = array_merge(
            array($argv[0]),  // wget binary
            array('--directory-prefix=' . $this->job_dir),
            array_slice($argv, 1)  // rest of arguments
        );
        
        // Save command for reference
        file_put_contents($this->job_dir . '/command.txt', implode(' ', $argv_with_dir));
        
        // Prepare descriptors
        $descriptors = array(
            0 => array('pipe', 'r'),  // stdin
            1 => array('file', $this->job_dir . '/stdout.log', 'a'), // stdout
            2 => array('file', $this->job_dir . '/stdout.log', 'a')  // stderr (combined)
        );
        
        // Environment with expanded PATH for macOS homebrew
        $env = array(
            'LC_ALL' => 'C',
            'PATH' => '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin'
        );
        
        // Start process with enforced directory
        $process = proc_open($argv_with_dir, $descriptors, $pipes, $this->job_dir, $env);
        
        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start wget process');
        }
        
        // Close stdin
        fclose($pipes[0]);
        
        // Get process info and save PID
        $proc_status = proc_get_status($process);
        $status['pid'] = $proc_status['pid'];
        $this->update_status($status);
        
        // Monitor process with timeout
        $start_time = time();
        while (true) {
            $proc_status = proc_get_status($process);
            
            if (!$proc_status['running']) {
                break;
            }
            
            // Check timeout
            if (time() - $start_time > $this->max_runtime) {
                // Kill process
                proc_terminate($process, 9);
                $status['status'] = 'timeout';
                $status['completed'] = time();
                $this->update_status($status);
                proc_close($process);
                throw new RuntimeException('Process exceeded maximum runtime');
            }
            
            // Check log size
            $log_file = $this->job_dir . '/stdout.log';
            if (file_exists($log_file)) {
                $log_size = filesize($log_file);
                if ($log_size !== false && $log_size > $this->max_log_size) {
                    proc_terminate($process, 9);
                    $status['status'] = 'killed';
                    $status['completed'] = time();
                    $this->update_status($status);
                    proc_close($process);
                    throw new RuntimeException('Log file exceeded maximum size');
                }
            }
            
            // Brief sleep
            usleep(100000); // 100ms
        }
        
        // Get exit code
        $exit_code = proc_close($process);
        
        // Update final status (treat non-zero exit as completed_with_errors)
        $status['status'] = ($exit_code === 0) ? 'completed' : 'completed_with_errors';
        $status['completed'] = time();
        $status['exit_code'] = $exit_code;
        $this->update_status($status);
        
        return $exit_code === 0;
    }
    
    /**
     * Extract URLs from wget output
     */
    public function extract_urls() {
        $log_file = $this->job_dir . '/stdout.log';
        if (!file_exists($log_file)) {
            return array();
        }
        
        $content = file_get_contents($log_file);
        $urls = array();
        
        // Primary pattern: --HH:MM:SS-- <URL>
        if (preg_match_all('/--\d{2}:\d{2}:\d{2}--\s+(\S+)/', $content, $matches)) {
            $urls = array_merge($urls, $matches[1]);
        }
        
        // Fallback: generic URL pattern
        if (preg_match_all('/\bhttps?:\/\/[^\s"\'<>]+/', $content, $matches)) {
            $urls = array_merge($urls, $matches[0]);
        }
        
        // Clean and deduplicate
        $urls = array_map(function($url) {
            // Remove trailing punctuation
            return rtrim($url, '.,;:!?)');
        }, $urls);
        
        $urls = array_unique($urls);
        $urls = array_values($urls); // Re-index
        
        // Save to JSON
        file_put_contents($this->job_dir . '/urls.json', json_encode($urls, JSON_PRETTY_PRINT));
        
        return $urls;
    }
    
    /**
     * Get log tail
     */
    public function get_log_tail($offset = 0) {
        $log_file = $this->job_dir . '/stdout.log';
        
        if (!file_exists($log_file)) {
            return array(
                'content' => '',
                'offset' => 0
            );
        }
        
        $handle = fopen($log_file, 'r');
        if (!$handle) {
            return array(
                'content' => '',
                'offset' => $offset
            );
        }
        
        // Seek to offset
        fseek($handle, $offset);
        
        // Read new content
        $content = stream_get_contents($handle);
        $new_offset = ftell($handle);
        fclose($handle);
        
        return array(
            'content' => $content,
            'offset' => $new_offset
        );
    }
    
    /**
     * Update job status
     */
    private function update_status($status) {
        file_put_contents(
            $this->job_dir . '/status.json', 
            json_encode($status, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Public: merge arbitrary metadata into status.json
     */
    public function set_metadata($meta) {
        $status = $this->get_status();
        if (!is_array($status)) { $status = array(); }
        if (!is_array($meta)) { $meta = array(); }
        $status = array_merge($status, $meta);
        $this->update_status($status);
    }
    
    /**
     * Get job status
     */
    public function get_status() {
        $status_file = $this->job_dir . '/status.json';
        if (!file_exists($status_file)) {
            return null;
        }
        return json_decode(file_get_contents($status_file), true);
    }
    
    /**
     * Set job directory for existing job
     */
    public function set_job($job_id) {
        $this->job_id = $job_id;
        $upload_dir = wp_upload_dir();
        $this->job_dir = $upload_dir['basedir'] . '/wgetta/jobs/' . $job_id;
        
        if (!is_dir($this->job_dir)) {
            throw new RuntimeException('Job not found: ' . $job_id);
        }
    }
    
    /**
     * Test POSIX ERE pattern match using grep
     */
    public static function wgetta_posix_match($pattern, $subject) {
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );
        
        $process = proc_open(
            array('grep', '-E', '-q', '--', $pattern),
            $descriptors,
            $pipes,
            null,
            array(
                'LC_ALL' => 'C',
                'PATH' => '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin'
            )
        );
        
        if (!is_resource($process)) {
            return false;
        }
        
        // Write subject to stdin
        fwrite($pipes[0], $subject . "\n");
        fclose($pipes[0]);
        
        // Read and close outputs
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        
        // Return true if grep found match (exit code 0)
        return proc_close($process) === 0;
    }

    /**
     * Validate a POSIX ERE pattern using grep. Returns array [ok(bool), error(string|null)]
     */
    public static function wgetta_validate_pattern($pattern) {
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );

        $process = proc_open(
            array('grep', '-E', '-q', '--', $pattern),
            $descriptors,
            $pipes,
            null,
            array(
                'LC_ALL' => 'C',
                'PATH' => '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin'
            )
        );

        if (!is_resource($process)) {
            return array('ok' => false, 'error' => 'Failed to invoke grep for validation');
        }

        // Write empty input
        fwrite($pipes[0], "\n");
        fclose($pipes[0]);
        
        // Drain outputs
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $code = proc_close($process);
        if ($code === 2) {
            return array('ok' => false, 'error' => trim($stderr));
        }
        return array('ok' => true, 'error' => null);
    }
    
    /**
     * Generate manifest after execution
     */
    public function generate_manifest() {
        // Run find command to list all downloaded files
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );
        
        $process = proc_open(
            array('find', '.', '-type', 'f'),
            $descriptors,
            $pipes,
            $this->job_dir,
            array(
                'LC_ALL' => 'C',
                'PATH' => '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin'
            )
        );
        
        if (!is_resource($process)) {
            return array();
        }
        
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        
        // Parse output
        $files = array_filter(explode("\n", $output));
        $files = array_map(function($file) {
            // Remove leading ./
            return ltrim($file, './');
        }, $files);
        
        // Exclude our own files
        $files = array_filter($files, function($file) {
            return !in_array($file, array('command.txt', 'stdout.log', 'status.json', 'urls.json', 'manifest.txt'));
        });
        
        // Save manifest
        file_put_contents($this->job_dir . '/manifest.txt', implode("\n", $files));
        
        return $files;
    }

    /**
     * Create a ZIP archive of the job directory contents (excluding internal files)
     */
    public function create_archive_zip($archive_name = 'archive.zip') {
        $zip_path = $this->job_dir . '/' . $archive_name;

        // Build file list similar to manifest filtering
        $files = $this->generate_manifest();

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return false;
            }
            foreach ($files as $rel) {
                $abs = $this->job_dir . '/' . ltrim($rel, '/');
                if (is_file($abs)) {
                    $zip->addFile($abs, $rel);
                }
            }
            // Optionally include manifest
            if (file_exists($this->job_dir . '/manifest.txt')) {
                $zip->addFile($this->job_dir . '/manifest.txt', 'manifest.txt');
            }
            $zip->close();
            return file_exists($zip_path) ? $zip_path : false;
        }

        // Fallback to system zip
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );
        $cmd = array('zip', '-rq', $zip_path);
        $process = proc_open($cmd, $descriptors, $pipes, $this->job_dir, array(
            'LC_ALL' => 'C',
            'PATH' => '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin'
        ));
        if (!is_resource($process)) { return false; }
        fclose($pipes[0]);
        stream_get_contents($pipes[1]); fclose($pipes[1]);
        stream_get_contents($pipes[2]); fclose($pipes[2]);
        proc_close($process);
        return file_exists($zip_path) ? $zip_path : false;
    }
}