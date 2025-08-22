<?php
if (!defined('ABSPATH')) { exit; }

$upload_dir = wp_upload_dir();
$jobs_root = trailingslashit($upload_dir['basedir']) . 'wgetta/jobs';

$rows = array();
if (is_dir($jobs_root)) {
    $dirs = array_filter(glob($jobs_root . '/*', GLOB_ONLYDIR), 'is_dir');
    usort($dirs, function($a, $b) { return filemtime($b) - filemtime($a); });
    foreach ($dirs as $dir) {
        $id = basename($dir);
        $status = null;
        $status_file = $dir . '/status.json';
        if (file_exists($status_file)) {
            $status = json_decode(file_get_contents($status_file), true);
        }
        $manifest = $dir . '/manifest.txt';
        $files = 0;
        if (file_exists($manifest)) {
            $files = count(array_filter(array_map('trim', explode("\n", file_get_contents($manifest)))));
        }
        $zip_url = null;
        if (file_exists($dir . '/archive.zip') && !empty($upload_dir['baseurl'])) {
            $zip_url = trailingslashit($upload_dir['baseurl']) . 'wgetta/jobs/' . $id . '/archive.zip';
        }
        $rows[] = array(
            'id' => $id,
            'status' => $status ? ($status['status'] ?? '') : '',
            'files' => $files,
            'path' => $dir,
            'zip_url' => $zip_url,
            'time' => filemtime($dir)
        );
    }
}
?>

<div class="wrap">
    <h1>History</h1>

    <div class="wgetta-card">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Job</th>
                    <th>Status</th>
                    <th>Files</th>
                    <th>Path</th>
                    <th>Archive</th>
                    <th>Modified</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)) : ?>
                    <tr><td colspan="6">No previous executions found.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><code><?php echo esc_html($r['id']); ?></code></td>
                        <td><?php echo esc_html($r['status']); ?></td>
                        <td><?php echo (int) $r['files']; ?></td>
                        <td><code><?php echo esc_html($r['path']); ?></code></td>
                        <td><?php if ($r['zip_url']) : ?><a href="<?php echo esc_url($r['zip_url']); ?>" target="_blank">Download</a><?php endif; ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $r['time'])); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

