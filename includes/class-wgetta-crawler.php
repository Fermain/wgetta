<?php

if (!defined('ABSPATH')) { exit; }

class Wgetta_Crawler {

	public function discover($seed_urls, $allowed_hosts = array(), $max_depth = 1, $max_pages = 1000, $log_file = null) {
		$queue = array();
		$seen = array();
		$found = array();
		$allowed = array_values(array_unique(array_filter(array_map('strtolower', (array) $allowed_hosts))));

		foreach ((array) $seed_urls as $u) {
			$u = $this->normalize($u);
			if ($u) { $queue[] = array($u, 0); $seen[$u] = true; }
		}

		while (!empty($queue) && count($found) < $max_pages) {
			list($url, $depth) = array_shift($queue);
			$this->log($log_file, 'GET ' . $url);
			$res = wp_remote_get($url, array('timeout' => 12, 'redirection' => 5, 'headers' => array('User-Agent' => 'Wgetta-Crawler ' . home_url('/'))));
			if (is_wp_error($res)) { $this->log($log_file, 'ERR ' . $res->get_error_message()); continue; }
			$code = wp_remote_retrieve_response_code($res);
			$body = wp_remote_retrieve_body($res);
			$ct = wp_remote_retrieve_header($res, 'content-type');
			$headers = wp_remote_retrieve_headers($res);
			$this->log($log_file, 'HTTP ' . $code);
			if ($code >= 400) { continue; }
			$found[] = $url;
			if ($depth >= $max_depth) { continue; }
			if ($this->is_html($ct)) {
				$links = $this->extract_links($url, $body);
				foreach ($links as $lnk) {
					if (!$this->is_allowed($lnk, $allowed)) { continue; }
					if (!isset($seen[$lnk])) { $queue[] = array($lnk, $depth + 1); $seen[$lnk] = true; }
				}
			} else if ($this->is_json($ct)) {
				// Enqueue URLs found within JSON bodies
				foreach ($this->extract_json_urls($body) as $lnk) {
					if (!$this->is_allowed($lnk, $allowed)) { continue; }
					if (!isset($seen[$lnk])) { $queue[] = array($lnk, $depth + 1); $seen[$lnk] = true; }
				}
				// Parse JSON HAL-style _links.next.href
				foreach ($this->extract_json_next_links($body) as $nextUrl) {
					if ($this->is_allowed($nextUrl, $allowed) && !isset($seen[$nextUrl])) { $queue[] = array($nextUrl, $depth + 1); $seen[$nextUrl] = true; }
				}
				// Parse REST pagination from headers: Link: <...>; rel="next"
				foreach ($this->extract_link_header_next($headers) as $nextUrl) {
					if ($this->is_allowed($nextUrl, $allowed) && !isset($seen[$nextUrl])) { $queue[] = array($nextUrl, $depth + 1); $seen[$nextUrl] = true; }
				}
				// WordPress X-WP-TotalPages pagination: enqueue page=2..N
				$tp = $this->get_total_pages($headers);
				if ($tp > 1) {
					$base = $this->url_without_query_param($url, 'page');
					$cur = max(1, (int) $this->get_query_param($url, 'page'));
					$maxFollow = min($tp, 50);
					for ($p = $cur + 1; $p <= $maxFollow; $p++) {
						$u = $base . (strpos($base, '?') === false ? '?' : '&') . 'page=' . $p;
						if (!isset($seen[$u]) && $this->is_allowed($u, $allowed)) { $queue[] = array($u, $depth + 1); $seen[$u] = true; }
					}
				}
				// From API index, discover wp/v2 collections (dynamic)
				if ($this->looks_like_api_index($url)) {
					$collections = $this->extract_wp_json_collections($body, $url);
					foreach ($collections as $col) {
						$u = $this->add_or_set_query_param($col, 'per_page', '100');
						if (!isset($seen[$u]) && $this->is_allowed($u, $allowed)) { $queue[] = array($u, $depth + 1); $seen[$u] = true; }
					}
				}
			}
		}

		return array_values(array_unique($found));
	}

	private function extract_links($base_url, $html) {
		$urls = array();
		libxml_use_internal_errors(true);
		$doc = new DOMDocument();
		$doc->loadHTML($html);
		$targets = array(
			array('tag' => 'a', 'attr' => 'href'),
			array('tag' => 'img', 'attr' => 'src'),
			array('tag' => 'script', 'attr' => 'src'),
			array('tag' => 'link', 'attr' => 'href'),
		);
		foreach ($targets as $t) {
			$nodes = $doc->getElementsByTagName($t['tag']);
			for ($i = 0; $i < $nodes->length; $i++) {
				$val = $nodes->item($i)->getAttribute($t['attr']);
				if ($val === '') { continue; }
				if (strpos($val, 'javascript:') === 0 || strpos($val, 'mailto:') === 0 || strpos($val, '#') === 0 || strpos($val, 'data:') === 0) { continue; }
				$abs = $this->to_absolute($base_url, $val);
				if ($abs) { $urls[] = $abs; }
			}
		}
		// Inline style attributes
		$all = $doc->getElementsByTagName('*');
		for ($i = 0; $i < $all->length; $i++) {
			$node = $all->item($i);
			$style = $node->getAttribute('style');
			if ($style) {
				foreach ($this->extract_css_urls($style) as $u) {
					$abs = $this->to_absolute($base_url, $u);
					if ($abs) { $urls[] = $abs; }
				}
			}
		}
		return array_values(array_unique($urls));
	}

	private function extract_css_urls($css) {
		$matches = array();
		$urls = array();
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
		// Heuristic: collect http/https URLs present in JSON strings
		if (preg_match_all('#https?://[^\s"\\<>]+#i', $json_text, $m)) {
			foreach ($m[0] as $u) { $urls[] = $u; }
		}
		// Also traverse simple REST collections with "next" pagination links
		if (preg_match('#"next"\s*:\s*"(https?://[^"]+)"#i', $json_text, $mm)) {
			$urls[] = $mm[1];
		}
		return array_values(array_unique($urls));
	}

	private function extract_json_next_links($json_text) {
		$urls = array();
		// Simple _links.next.href or links.next
		if (preg_match_all('#"_links"\s*:\s*\{[^}]*?"next"\s*:\s*\{[^}]*?"href"\s*:\s*"(https?://[^"]+)"#i', $json_text, $m)) {
			foreach ($m[1] as $u) { $urls[] = $u; }
		}
		if (preg_match_all('#"next"\s*:\s*\{[^}]*?"href"\s*:\s*"(https?://[^"]+)"#i', $json_text, $m2)) {
			foreach ($m2[1] as $u) { $urls[] = $u; }
		}
		return array_values(array_unique($urls));
	}

	private function extract_link_header_next($headers) {
		$urls = array();
		if (is_array($headers)) {
			$h = isset($headers['link']) ? $headers['link'] : (isset($headers['Link']) ? $headers['Link'] : null);
			if (is_array($h)) { $h = implode(', ', $h); }
			if (is_string($h) && preg_match_all('/<([^>]+)>;\s*rel="next"/i', $h, $m)) {
				foreach ($m[1] as $u) { $urls[] = $u; }
			}
		}
		return array_values(array_unique($urls));
	}

	private function get_total_pages($headers) {
		if (is_array($headers)) {
			foreach (array('X-WP-TotalPages', 'x-wp-totalpages') as $k) {
				if (isset($headers[$k])) { return (int) $headers[$k]; }
			}
		}
		return 0;
	}

	private function looks_like_api_index($url) {
		return (bool) preg_match('#/wp-json/?$#i', $url);
	}


	private function extract_wp_json_collections($json_text, $api_root) {
		$api_root = rtrim($api_root, '/') . '/';
		$base = $api_root . 'wp/v2/';
		$collections = array();
		// Discover any wp/v2 routes in the index body
		if (preg_match_all('#"(wp\/v2\/[a-zA-Z0-9_-]+)"#', $json_text, $m)) {
			foreach ($m[1] as $route) {
				$collections[] = $base . basename($route);
			}
		}
		// Fallback to common ones
		if (empty($collections)) {
			$collections = array($base . 'posts', $base . 'pages', $base . 'categories', $base . 'tags', $base . 'users');
		}
		return array_values(array_unique($collections));
	}

	private function get_query_param($url, $key) {
		$p = wp_parse_url($url);
		if (!$p || !isset($p['query'])) { return null; }
		parse_str($p['query'], $q);
		return isset($q[$key]) ? $q[$key] : null;
	}

	private function url_without_query_param($url, $key) {
		$p = wp_parse_url($url);
		if (!$p) { return $url; }
		$query = array(); if (isset($p['query'])) { parse_str($p['query'], $query); unset($query[$key]); }
		$scheme = $p['scheme']; $host = $p['host']; $port = isset($p['port']) ? (':' . $p['port']) : '';
		$path = isset($p['path']) ? $p['path'] : '/';
		$qs = http_build_query($query);
		return $scheme . '://' . $host . $port . $path . ($qs ? ('?' . $qs) : '');
	}

	private function add_or_set_query_param($url, $key, $value) {
		$p = wp_parse_url($url);
		if (!$p) { return $url; }
		$query = array(); if (isset($p['query'])) { parse_str($p['query'], $query); }
		$query[$key] = $value;
		$scheme = $p['scheme']; $host = $p['host']; $port = isset($p['port']) ? (':' . $p['port']) : '';
		$path = isset($p['path']) ? $p['path'] : '/';
		$qs = http_build_query($query);
		return $scheme . '://' . $host . $port . $path . ($qs ? ('?' . $qs) : '');
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

	private function is_allowed($url, $allowed_hosts) {
		if (empty($allowed_hosts)) { return true; }
		$h = parse_url($url, PHP_URL_HOST);
		return $h && in_array(strtolower($h), $allowed_hosts, true);
	}

	private function is_html($ct) {
		return is_string($ct) && stripos($ct, 'text/html') !== false;
	}

	private function is_json($ct) {
		return is_string($ct) && (stripos($ct, 'application/json') !== false || stripos($ct, '+json') !== false);
	}

	private function normalize($url) {
		$url = trim($url);
		return $url !== '' ? esc_url_raw($url) : null;
	}

	private function log($log_file, $line) {
		if (!$log_file) { return; }
		@file_put_contents($log_file, $line . "\n", FILE_APPEND);
	}
}


