<?php
/**
 * Uninstall cleanup for Privacy Lite Video Embeds for YouTube.
 *
 * @package Privacy_Lite_Video_Embeds
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

function plve_uninstall_delete_thumbnail_cache(): void {
    $plve_upload_dir = wp_upload_dir();
    if (empty($plve_upload_dir['basedir'])) {
        return;
    }

    $plve_dir = trailingslashit($plve_upload_dir['basedir']) . 'privacy-lite-video-embeds';
    $plve_files = is_dir($plve_dir) && is_readable($plve_dir) ? glob(trailingslashit($plve_dir) . '*.jpg') : [];

    foreach (is_array($plve_files) ? $plve_files : [] as $plve_file) {
        if (is_file($plve_file)) {
            wp_delete_file($plve_file);
        }
    }
}

function plve_uninstall_delete_failed_transients(): void {
    $plve_keys = get_option('plve_failed_thumbnail_keys', []);

    if (is_array($plve_keys)) {
        foreach ($plve_keys as $plve_key) {
            delete_transient(sanitize_key((string) $plve_key));
        }
    }

    delete_option('plve_failed_thumbnail_keys');
}

function plve_uninstall_for_current_site(): void {
    delete_option('plve_settings');
    plve_uninstall_delete_failed_transients();
    plve_uninstall_delete_thumbnail_cache();
}

if (is_multisite()) {
    $plve_site_ids = get_sites(['fields' => 'ids']);
    foreach ($plve_site_ids as $plve_site_id) {
        switch_to_blog((int) $plve_site_id);
        plve_uninstall_for_current_site();
        restore_current_blog();
    }
} else {
    plve_uninstall_for_current_site();
}
