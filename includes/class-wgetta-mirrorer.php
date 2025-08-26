<?php

if (!defined('ABSPATH')) { exit; }

class Wgetta_Mirrorer {

    private $max_depth_recursive = 3;
    private $max_pages = 20000;

    public function mirror($urls, $destination, $recursive, $log_file, $extra_reject_regex = '') {
        $seeds = array_values(array_filter(array_map('trim', (array) $urls)));
        if (empty($seeds)) { return false; }

        wp_mkdir_p($destination);

        $allowed_hosts = array();
        foreach ($seeds as $u) { $h = parse_url($u, PHP_URL_HOST); if ($h && is_string($h)) { $allowed_hosts[] = strtolower($h); } }
        $allowed_hosts = array_values(array_unique($allowed_hosts));

        $reject = is_string($extra_reject_regex) ? trim($extra_reject_regex) : '';

        $queue = array();
        $seen = array();
        $saved = array();

        foreach ($seeds as $u) { $queue[] = array($u, 0, 'page'); $seen[$u] = true; }

        $max_depth = $recursive ? $this->max_depth_recursive : 0;

        while (!empty($queue) && count($saved) < $this->max_pages) {
            list($url, $depth, $kind) = array_shift($queue);
            if ($reject !== '' && $this->matches_reject($reject, $url)) { $this->log($log_file, 'REJECT ' . $url); continue; }
            if (!$this->is_allowed_host($allowed_hosts, $url)) { continue; }

            $this->log($log_file, 'GET ' . $url);
            $res = wp_remote_get($url, array('timeout' => 20, 'redirection' => 5, 'headers' => array('User-Agent' => 'Wgetta-Mirror ' . home_url('/'))));
            if (is_wp_error($res)) { $this->log($log_file, 'ERR ' . $res->get_error_message()); continue; }
            $code = wp_remote_retrieve_response_code($res);
            $body = wp_remote_retrieve_body($res);
            $ct = wp_remote_retrieve_header($res, 'content-type') ?: '';
            $this->log($log_file, 'HTTP ' . $code);
            if ($code >= 400) { continue; }

            $rel = $this->map_url_to_path($url, $ct);
            $abs = rtrim($destination, '/') . '/' . $rel;
            $dir = dirname($abs);
            if (!is_dir($dir)) { wp_mkdir_p($dir); }

            if ($this->is_html($ct)) {
                list($rewritten, $assets, $page_links) = $this->rewrite_html_and_collect($url, $body);
                file_put_contents($abs, $rewritten);
                $saved[] = $rel;
                // Always enqueue assets (images, scripts, styles)
                foreach ($assets as $a) {
                    if (!isset($seen[$a]) && $this->is_allowed_host($allowed_hosts, $a) && ($reject === '' || !$this->matches_reject($reject, $a))) {
                        $queue[] = array($a, $depth, 'asset');
                        $seen[$a] = true;
                    }
                }
                // Follow page links only if within depth
                if ($depth < $max_depth) {
                    foreach ($page_links as $p) {
                        if (!isset($seen[$p]) && $this->is_allowed_host($allowed_hosts, $p) && ($reject === '' || !$this->matches_reject($reject, $p))) {
                            $queue[] = array($p, $depth + 1, 'page');
                            $seen[$p] = true;
                        }
                    }
                }
            } else {
                file_put_contents($abs, $body);
                $saved[] = $rel;
                // If CSS, parse url(...) to fetch nested assets
                if ($this->is_css($ct)) {
                    foreach ($this->extract_css_urls($body) as $u) {
                        $absu = $this->to_absolute($url, $u);
                        if ($absu && !isset($seen[$absu]) && $this->is_allowed_host($allowed_hosts, $absu) && ($reject === '' || !$this->matches_reject($reject, $absu))) {
                            $queue[] = array($absu, $depth, 'asset');
                            $seen[$absu] = true;
                        }
                    }
                } else if ($this->is_json($ct)) {
                    foreach ($this->extract_json_urls($body) as $u) {
                        if (!isset($seen[$u]) && $this->is_allowed_host($allowed_hosts, $u) && ($reject === '' || !$this->matches_reject($reject, $u))) {
                            $queue[] = array($u, $depth + 1, 'page');
                            $seen[$u] = true;
                        }
                    }
                }
            }
        }

        return true;
    }

    private function rewrite_html_and_collect($base_url, $html) {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML($html);
        $assets = array();
        $pages = array();

        // a href (pages)
        $a = $doc->getElementsByTagName('a');
        for ($i = 0; $i < $a->length; $i++) {
            $el = $a->item($i);
            $href = $el->getAttribute('href');
            if ($href === '' || strpos($href, 'javascript:') === 0 || strpos($href, 'mailto:') === 0 || strpos($href, '#') === 0) { continue; }
            $abs = $this->to_absolute($base_url, $href);
            if (!$abs) { continue; }
            $mapped = $this->map_url_to_path($abs, 'text/html');
            $el->setAttribute('href', $this->to_root_relative($mapped));
            $pages[] = $abs;
        }

        // img/src, script/src, link rel=stylesheet, link href (assets)
        $pairs = array(
            array('img', 'src'),
            array('source', 'srcset'),
            array('img', 'srcset'),
            array('script', 'src'),
            array('link', 'href'),
        );
        foreach ($pairs as $p) {
            $nodes = $doc->getElementsByTagName($p[0]);
            for ($i = 0; $i < $nodes->length; $i++) {
                $el = $nodes->item($i);
                $val = $el->getAttribute($p[1]);
                if ($val === '') { continue; }
                if ($p[1] === 'srcset') {
                    $candidates = preg_split('/\s*,\s*/', $val);
                    $rebuilt = array();
                    foreach ($candidates as $cand) {
                        $parts = preg_split('/\s+/', trim($cand));
                        if (empty($parts[0])) { continue; }
                        $abs = $this->to_absolute($base_url, $parts[0]);
                        if (!$abs) { continue; }
                        $assets[] = $abs;
                        $mapped = $this->map_url_to_path($abs, null);
                        $rebuilt[] = $this->to_root_relative($mapped) . (isset($parts[1]) ? (' ' . $parts[1]) : '');
                    }
                    if (!empty($rebuilt)) { $el->setAttribute('srcset', implode(', ', $rebuilt)); }
                } else {
                    $abs = $this->to_absolute($base_url, $val);
                    if (!$abs) { continue; }
                    $assets[] = $abs;
                    $mapped = $this->map_url_to_path($abs, null);
                    if ($p[0] === 'link') {
                        $rel = strtolower($el->getAttribute('rel'));
                        if ($rel === 'stylesheet' || $rel === 'preload' || $rel === 'icon') {
                            $el->setAttribute('href', $this->to_root_relative($mapped));
                        }
                    } else {
                        $el->setAttribute($p[1], $this->to_root_relative($mapped));
                    }
                }
            }
        }

        // Inline style url(...)
        $all = $doc->getElementsByTagName('*');
        for ($i = 0; $i < $all->length; $i++) {
            $node = $all->item($i);
            $style = $node->getAttribute('style');
            if ($style) {
                $rebuilt = $style;
                foreach ($this->extract_css_urls($style) as $u) {
                    $abs = $this->to_absolute($base_url, $u);
                    if ($abs) {
                        $assets[] = $abs;
                        $mapped = $this->map_url_to_path($abs, null);
                        $rebuilt = str_replace($u, $this->to_root_relative($mapped), $rebuilt);
                    }
                }
                $node->setAttribute('style', $rebuilt);
            }
        }

        $out = $doc->saveHTML();
        return array($out ?: $html, array_values(array_unique($assets)), array_values(array_unique($pages)));
    }

    private function extract_css_urls($css) {
        $matches = array(); $urls = array();
        if (preg_match_all('#url\(([^)]+)\)#i', $css, $matches)) {
            foreach ($matches[1] as $m) {
                $u = trim($m, " \"'\t\n\r");
                if ($u !== '') { $urls[] = $u; }
            }
        }
        return $urls;
    }

    private function extract_json_urls($json_text) {
        $urls = array();
        if (preg_match_all('#https?://[^\s"\\<>]+#i', $json_text, $m)) {
            foreach ($m[0] as $u) { $urls[] = $u; }
        }
        if (preg_match('#"next"\s*:\s*"(https?://[^"]+)"#i', $json_text, $mm)) {
            $urls[] = $mm[1];
        }
        return array_values(array_unique($urls));
    }

    private function is_allowed_host($allowed_hosts, $url) {
        if (empty($allowed_hosts)) { return true; }
        $h = parse_url($url, PHP_URL_HOST);
        return $h && in_array(strtolower($h), $allowed_hosts, true);
    }

    private function matches_reject($pattern, $subject) {
        // Use PCRE here; patterns were validated earlier as POSIX ERE, but we accept common overlap
        return @preg_match('#' . $pattern . '#', $subject) === 1;
    }

    private function is_html($ct) {
        return is_string($ct) && stripos($ct, 'text/html') !== false;
    }

    private function is_css($ct) {
        return is_string($ct) && (stripos($ct, 'text/css') !== false || stripos($ct, '/css') !== false);
    }

    private function is_json($ct) {
        return is_string($ct) && (stripos($ct, 'application/json') !== false || stripos($ct, '+json') !== false);
    }

    private function to_root_relative($rel_path) {
        return '/' . ltrim($rel_path, '/');
    }

    private function to_absolute($base, $maybe) {
        $maybe = trim($maybe);
        if ($maybe === '') { return null; }
        if (preg_match('#^https?://#i', $maybe)) { return $maybe; }
        if (strpos($maybe, '//') === 0) { $sch = parse_url($base, PHP_URL_SCHEME) ?: 'https'; return $sch . ':' . $maybe; }
        $bp = wp_parse_url($base);
        if (!$bp || empty($bp['scheme']) || empty($bp['host'])) { return null; }
        $scheme = $bp['scheme']; $host = $bp['host']; $port = isset($bp['port']) ? ':' . $bp['port'] : '';
        $base_path = isset($bp['path']) ? $bp['path'] : '/';
        $path = ($maybe[0] === '/') ? $maybe : rtrim(dirname($base_path), '/') . '/' . $maybe;
        $path = preg_replace('#/\./#', '/', $path);
        while (strpos($path, '../') !== false) { $path = preg_replace('#[^/]+/\.\./#', '', $path, 1); }
        return $scheme . '://' . $host . $port . $path;
    }

    private function map_url_to_path($url, $content_type) {
        $parts = wp_parse_url($url);
        $host = isset($parts['host']) ? strtolower($parts['host']) : 'host';
        $path = isset($parts['path']) ? $parts['path'] : '/';
        $query = isset($parts['query']) ? $parts['query'] : '';
        $path = '/' . ltrim($path, '/');
        $leaf = basename($path);
        $is_json = $this->is_json($content_type) || preg_match('#/wp-json/?$#i', $path);
        if ($leaf === '' || $leaf === '/' || substr($path, -1) === '/') {
            $rel = $host . rtrim($path, '/') . ($is_json ? '/index.json' : '/index.html');
        } else if (strpos($leaf, '.') === false) {
            if ($content_type === null) {
                $rel = $host . rtrim($path, '/') . '/index.html';
            } else {
                $rel = $host . rtrim($path, '/') . ($is_json ? '/index.json' : '/index.html');
            }
        } else {
            $rel = $host . $path;
        }
        if ($query !== '') { $rel = rtrim($rel, '/') . '/_q_' . md5($query) . '/index.html'; }
        return ltrim($rel, '/');
    }

    private function log($log_file, $line) {
        if ($log_file) { @file_put_contents($log_file, $line . "\n", FILE_APPEND); }
    }
}


