<?php
/**
 * Uninstall cleanup for Privacy Lite Video Embeds for YouTube.
 *
 * @package Privacy_Lite_Video_Embeds
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

function plye_uninstall_delete_thumbnail_cache(): void {
    $plye_upload_dir = wp_upload_dir();
    if (empty($plye_upload_dir['basedir'])) {
        return;
    }

    $plye_dir = trailingslashit($plye_upload_dir['basedir']) . 'privacy-lite-video-embeds';
    $plye_files = is_dir($plye_dir) && is_readable($plye_dir) ? glob(trailingslashit($plye_dir) . '*.jpg') : [];

    foreach (is_array($plye_files) ? $plye_files : [] as $plye_file) {
        if (is_file($plye_file)) {
            wp_delete_file($plye_file);
        }
    }
}

function plye_uninstall_delete_failed_transients(): void {
    $plye_keys = get_option('plye_failed_thumbnail_keys', []);

    if (is_array($plye_keys)) {
        foreach ($plye_keys as $plye_key) {
            delete_transient(sanitize_key((string) $plye_key));
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
    $plye_site_ids = get_sites(['fields' => 'ids']);
    foreach ($plye_site_ids as $plye_site_id) {
        switch_to_blog((int) $plye_site_id);
        plye_uninstall_for_current_site();
        restore_current_blog();
    }
} else {
    plye_uninstall_for_current_site();
}
