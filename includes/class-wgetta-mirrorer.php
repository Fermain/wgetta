<?php

if (!defined('ABSPATH')) { exit; }

class Wgetta_Mirrorer {

    public function mirror($urls, $destination, $recursive, $log_file, $extra_reject_regex = '') {
        $urls = array_values(array_filter(array_map('trim', (array) $urls)));
        if (empty($urls)) { return false; }

        $domains = array();
        foreach ($urls as $u) {
            $h = parse_url($u, PHP_URL_HOST);
            if ($h && is_string($h)) { $domains[] = $h; }
        }
        $domains = array_values(array_unique($domains));

        $temp_destination = sys_get_temp_dir() . '/static-mirror-' . mt_rand(0, 99999);
        wp_mkdir_p($destination);

        $mirror_cookies = apply_filters('static_mirror_crawler_cookies', array('wp_static_mirror' => 1));
        $resource_domains = apply_filters('static_mirror_resource_domains', array());

        $allowed_domains = array_merge($resource_domains, $domains);

        $cookie_string = implode(';', array_map(function($v, $k){ return $k . '=' . $v; }, $mirror_cookies, array_keys($mirror_cookies)));

        $user_agent = 'WordPress/Static-Mirror; ' . get_bloginfo('url');

        foreach ($urls as $url) {
            $args = array(
                sprintf('--user-agent="%s"', $user_agent),
                '--no-clobber',
                '--page-requisites',
                '--convert-links',
                '--backup-converted',
                $recursive ? '--recursive' : '',
                '-erobots=off',
                '--restrict-file-names=windows',
                sprintf('--reject-regex "%s"', implode('|', array_filter(array(
                    '.+\/feed\/?$',
                    '.+\/wp-json\/?(.+)?$',
                    (is_string($extra_reject_regex) && $extra_reject_regex !== '' ? '(' . $extra_reject_regex . ')' : ''),
                )))),
                '--html-extension',
                '--content-on-error',
                '--trust-server-names',
                sprintf('--header "Cookie: %s"', $cookie_string),
                '--span-hosts',
                sprintf('--domains="%s"', implode(',', $allowed_domains)),
                sprintf('--directory-prefix=%s', escapeshellarg($temp_destination)),
            );

            if (defined('SM_NO_CHECK_CERT') && SM_NO_CHECK_CERT) {
                $args[] = '--no-check-certificate';
            }

            $cmd = sprintf('wget %s %s 2>&1', implode(' ', array_filter($args)), escapeshellarg(esc_url_raw($url)));

            $this->run_and_log($cmd, $log_file);

            if (!is_dir($temp_destination)) {
                throw new RuntimeException('wget returned no data');
            }
        }

        $this->move_directory(untrailingslashit($temp_destination), untrailingslashit($destination));

        return true;
    }

    private function run_and_log($cmd, $log_file) {
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('file', $log_file, 'a'),
            2 => array('file', $log_file, 'a'),
        );
        $env = array('LC_ALL' => 'C', 'PATH' => '/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin');
        $proc = proc_open($cmd, $descriptors, $pipes, null, $env);
        if (is_resource($proc)) {
            fclose($pipes[0]);
            proc_close($proc);
        }
    }

    private function move_directory($source, $dest) {
        $h = @opendir($source);
        if (!$h) { return false; }
        while (false !== ($file = readdir($h))) {
            if ($file === '.' || $file === '..') { continue; }
            if (is_dir($source . '/' . $file)) {
                wp_mkdir_p($dest . '/' . $file);
                $this->move_directory($source . '/' . $file, $dest . '/' . $file);
            } else {
                @copy($source . '/' . $file, $dest . '/' . $file);
                @unlink($source . '/' . $file);
            }
        }
        return true;
    }
}


