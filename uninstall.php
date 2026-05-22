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
    global $wpdb;

    $like = $wpdb->esc_like('_transient_plye_thumb_fail_') . '%';
    $timeout_like = $wpdb->esc_like('_transient_timeout_plye_thumb_fail_') . '%';

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $like,
            $timeout_like
        )
    );
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
