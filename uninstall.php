<?php
/**
 * Uninstall cleanup for Privacy Lite YouTube Embeds.
 *
 * @package Privacy_Lite_YouTube_Embeds
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

function plye_uninstall_delete_thumbnail_cache(): void {
    $upload_dir = wp_upload_dir();
    if (empty($upload_dir['basedir'])) {
        return;
    }

    $dir = trailingslashit($upload_dir['basedir']) . 'privacy-lite-youtube-embeds';
    $files = is_dir($dir) && is_readable($dir) ? glob(trailingslashit($dir) . '*.jpg') : [];

    foreach (is_array($files) ? $files : [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }

    if (is_dir($dir)) {
        @rmdir($dir);
    }
}

function plye_uninstall_delete_failed_transients(): void {
    $keys = get_option('plye_failed_thumbnail_keys', []);

    if (is_array($keys)) {
        foreach ($keys as $key) {
            delete_transient(sanitize_key((string) $key));
        }
    }

    delete_option('plye_failed_thumbnail_keys');
}

function plye_uninstall_for_current_site(): void {
    delete_option('plye_settings');
    plye_uninstall_delete_failed_transients();
    plye_uninstall_delete_thumbnail_cache();
}

if (is_multisite()) {
    $site_ids = get_sites(['fields' => 'ids']);
    foreach ($site_ids as $site_id) {
        switch_to_blog((int) $site_id);
        plye_uninstall_for_current_site();
        restore_current_blog();
    }
} else {
    plye_uninstall_for_current_site();
}
