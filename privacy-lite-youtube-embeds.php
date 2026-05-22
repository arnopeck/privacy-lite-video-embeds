<?php
/**
 * Plugin Name: Privacy Lite YouTube Embeds
 * Plugin URI: https://github.com/arnopeck/privacy-lite-youtube-embeds
 * Description: Replaces YouTube embeds with local thumbnails and loads the youtube-nocookie player only after user interaction.
 * Version: 1.0.0
 * Author: Arno Peck
 * Author URI: https://github.com/arnopeck
 * Text Domain: privacy-lite-youtube-embeds
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Privacy_Lite_YouTube_Embeds {
    private const VERSION = '1.0.0';
    private const OPTION_NAME = 'plye_settings';
    private const THUMB_DIR = 'privacy-lite-youtube-embeds';
    private const FAILED_THUMB_TTL = 12 * HOUR_IN_SECONDS;
    private const MAX_SCAN_POSTS = 50;
    private const DEFAULT_SUPPORT_URL = 'https://ko-fi.com/luminescenza';

    private static ?self $instance = null;

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'add_privacy_policy_suggestion']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_post_plye_scan_thumbnails', [$this, 'handle_scan_thumbnails']);
        add_action('admin_post_plye_clear_thumbnails', [$this, 'handle_clear_thumbnails']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('save_post', [$this, 'prime_post_thumbnails'], 20, 3);

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_plugin_action_links']);
        add_filter('render_block', [$this, 'filter_render_block'], 20, 2);
        add_filter('embed_oembed_html', [$this, 'filter_oembed_html'], 20, 4);
        add_filter('the_content', [$this, 'filter_content_iframes'], 20);
    }

    public function load_textdomain(): void {
        load_plugin_textdomain(
            'privacy-lite-youtube-embeds',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    public static function activate(): void {
        $upload_dir = wp_upload_dir();
        if (empty($upload_dir['basedir'])) {
            return;
        }

        $target = trailingslashit($upload_dir['basedir']) . self::THUMB_DIR;
        if (!is_dir($target)) {
            wp_mkdir_p($target);
        }
    }

    public function enqueue_assets(): void {
        if (is_admin() || is_feed()) {
            return;
        }

        wp_enqueue_style(
            'privacy-lite-youtube-embeds',
            plugins_url('assets/privacy-lite-youtube-embeds.css', __FILE__),
            [],
            self::VERSION
        );

        wp_enqueue_script(
            'privacy-lite-youtube-embeds',
            plugins_url('assets/privacy-lite-youtube-embeds.js', __FILE__),
            [],
            self::VERSION,
            true
        );
    }

    public function add_plugin_action_links(array $links): array {
        $settings_url = admin_url('options-general.php?page=privacy-lite-youtube-embeds');
        array_unshift(
            $links,
            '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'privacy-lite-youtube-embeds') . '</a>'
        );

        return $links;
    }

    public function register_settings(): void {
        register_setting(
            'plye_settings_group',
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->default_settings(),
            ]
        );

        add_settings_section(
            'plye_main_section',
            __('YouTube privacy replacement', 'privacy-lite-youtube-embeds'),
            function (): void {
                echo '<p>' . esc_html__('Replace YouTube embeds with local thumbnails and load the video player only after click.', 'privacy-lite-youtube-embeds') . '</p>';
            },
            'plye_settings'
        );

        add_settings_field('scope', __('Replacement scope', 'privacy-lite-youtube-embeds'), [$this, 'render_scope_field'], 'plye_settings', 'plye_main_section');
        add_settings_field('show_consent_text', __('Consent text', 'privacy-lite-youtube-embeds'), [$this, 'render_consent_toggle_field'], 'plye_settings', 'plye_main_section');
        add_settings_field('consent_text', __('Consent message', 'privacy-lite-youtube-embeds'), [$this, 'render_consent_text_field'], 'plye_settings', 'plye_main_section');
        add_settings_field('autoplay', __('Autoplay after click', 'privacy-lite-youtube-embeds'), [$this, 'render_autoplay_field'], 'plye_settings', 'plye_main_section');
    }

    public function add_privacy_policy_suggestion(): void {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = wp_kses_post(
            __(
                'This site uses Privacy Lite YouTube Embeds to display YouTube videos with a local placeholder. Before a visitor clicks a video placeholder, the visitor browser does not load the YouTube player or remote YouTube thumbnails. If the visitor chooses to play a video, the YouTube player is loaded from the privacy-enhanced youtube-nocookie.com domain and YouTube/Google may process data according to their own terms and privacy policy.',
                'privacy-lite-youtube-embeds'
            )
        );

        wp_add_privacy_policy_content('Privacy Lite YouTube Embeds', wpautop($content));
    }

    public function add_settings_page(): void {
        add_options_page(
            __('Privacy Lite YouTube Embeds', 'privacy-lite-youtube-embeds'),
            __('Privacy Lite YouTube', 'privacy-lite-youtube-embeds'),
            'manage_options',
            'privacy-lite-youtube-embeds',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <div style="max-width:1120px; margin-bottom:16px;">
                <h1 style="margin:0 0 6px;"><?php echo esc_html__('Privacy Lite YouTube Embeds', 'privacy-lite-youtube-embeds'); ?></h1>
                <p style="margin:0; color:#646970; font-size:14px;"><?php echo esc_html__('Fast YouTube embeds. Nothing loads until click.', 'privacy-lite-youtube-embeds'); ?></p>
            </div>

            <?php $this->render_admin_tool_notice(); ?>

            <div class="notice notice-info inline" style="max-width:1120px; margin-top:0;">
                <p><strong><?php echo esc_html__('Privacy behavior', 'privacy-lite-youtube-embeds'); ?></strong></p>
                <p><?php echo esc_html__('Before click, the frontend loads only local HTML, CSS, JavaScript and locally cached thumbnails. YouTube is loaded from youtube-nocookie.com only after the visitor clicks the placeholder.', 'privacy-lite-youtube-embeds'); ?></p>
                <p><?php echo esc_html__('To verify this, open your browser Network panel and reload a page with a YouTube embed: before the click there should be no requests to youtube.com, youtube-nocookie.com, ytimg.com, googlevideo.com, google.com or gstatic.com.', 'privacy-lite-youtube-embeds'); ?></p>
            </div>

            <div style="max-width:1120px; margin:12px 0 18px; display:flex; justify-content:flex-end;">
                <?php $this->render_support_badge(); ?>
            </div>

            <form method="post" action="options.php" style="max-width:1120px;">
                <?php
                settings_fields('plye_settings_group');
                do_settings_sections('plye_settings');
                submit_button();
                ?>
            </form>

            <hr style="max-width:1120px; margin:26px 0 16px;">
            <div style="max-width:1120px;">
                <h2><?php echo esc_html__('Thumbnail tools', 'privacy-lite-youtube-embeds'); ?></h2>
                <p><?php echo esc_html__('Use these tools for existing content, troubleshooting, or after changing many YouTube embeds.', 'privacy-lite-youtube-embeds'); ?></p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:1em;">
                    <input type="hidden" name="action" value="plye_scan_thumbnails">
                    <?php wp_nonce_field('plye_scan_thumbnails'); ?>
                    <?php submit_button(__('Scan content and generate missing thumbnails', 'privacy-lite-youtube-embeds'), 'secondary', 'submit', false); ?>
                    <p class="description">
                        <?php
                        printf(
                            esc_html__('Scans up to %d published public posts/pages per run and downloads missing local thumbnails.', 'privacy-lite-youtube-embeds'),
                            self::MAX_SCAN_POSTS
                        );
                        ?>
                    </p>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="plye_clear_thumbnails">
                    <?php wp_nonce_field('plye_clear_thumbnails'); ?>
                    <?php
                    submit_button(
                        __('Clear local thumbnail cache', 'privacy-lite-youtube-embeds'),
                        'delete',
                        'submit',
                        false,
                        [
                            'onclick' => "return confirm('" . esc_js(__('Delete all locally cached YouTube thumbnails?', 'privacy-lite-youtube-embeds')) . "');",
                        ]
                    );
                    ?>
                    <p class="description"><?php echo esc_html__('Deletes locally cached thumbnail files and clears failed-download retry markers. Thumbnails will be regenerated when content is scanned, saved, or viewed.', 'privacy-lite-youtube-embeds'); ?></p>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_scope_field(): void {
        $settings = $this->settings();
        ?>
        <select name="<?php echo esc_attr(self::OPTION_NAME); ?>[scope]">
            <option value="gutenberg" <?php selected($settings['scope'], 'gutenberg'); ?>><?php echo esc_html__('Only Gutenberg YouTube embed blocks', 'privacy-lite-youtube-embeds'); ?></option>
            <option value="all" <?php selected($settings['scope'], 'all'); ?>><?php echo esc_html__('All YouTube videos found in content', 'privacy-lite-youtube-embeds'); ?></option>
        </select>
        <p class="description"><?php echo esc_html__('Use “All” to also replace classic oEmbeds and manually pasted YouTube iframes.', 'privacy-lite-youtube-embeds'); ?></p>
        <?php
    }

    public function render_consent_toggle_field(): void {
        $settings = $this->settings();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[show_consent_text]" value="1" <?php checked($settings['show_consent_text']); ?>>
            <?php echo esc_html__('Show a short privacy message inside the placeholder.', 'privacy-lite-youtube-embeds'); ?>
        </label>
        <?php
    }

    public function render_consent_text_field(): void {
        $settings = $this->settings();
        ?>
        <textarea class="large-text" rows="3" name="<?php echo esc_attr(self::OPTION_NAME); ?>[consent_text]"><?php echo esc_textarea($settings['consent_text']); ?></textarea>
        <p class="description"><?php echo esc_html__('Displayed only if the consent text option is enabled.', 'privacy-lite-youtube-embeds'); ?></p>
        <?php
    }

    public function render_autoplay_field(): void {
        $settings = $this->settings();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[autoplay]" value="1" <?php checked($settings['autoplay']); ?>>
            <?php echo esc_html__('Start playback immediately after the user clicks the placeholder.', 'privacy-lite-youtube-embeds'); ?>
        </label>
        <?php
    }

    public function sanitize_settings($input): array {
        $defaults = $this->default_settings();
        $input = is_array($input) ? $input : [];

        $scope = isset($input['scope']) ? sanitize_key((string) $input['scope']) : $defaults['scope'];
        if (!in_array($scope, ['gutenberg', 'all'], true)) {
            $scope = $defaults['scope'];
        }

        return [
            'scope' => $scope,
            'show_consent_text' => !empty($input['show_consent_text']),
            'consent_text' => isset($input['consent_text']) ? sanitize_textarea_field((string) $input['consent_text']) : $defaults['consent_text'],
            'autoplay' => !empty($input['autoplay']),
        ];
    }

    public function handle_scan_thumbnails(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to run this tool.', 'privacy-lite-youtube-embeds'));
        }

        check_admin_referer('plye_scan_thumbnails');
        $result = $this->scan_content_for_thumbnails(self::MAX_SCAN_POSTS);

        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'privacy-lite-youtube-embeds',
                    'plye_notice' => 'scan',
                    'plye_posts' => absint($result['posts_scanned']),
                    'plye_videos' => absint($result['videos_found']),
                    'plye_existing' => absint($result['existing']),
                    'plye_generated' => absint($result['generated']),
                    'plye_failed' => absint($result['failed']),
                ],
                admin_url('options-general.php')
            )
        );
        exit;
    }

    public function handle_clear_thumbnails(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to run this tool.', 'privacy-lite-youtube-embeds'));
        }

        check_admin_referer('plye_clear_thumbnails');
        $deleted = $this->clear_thumbnail_cache();
        $this->clear_failed_thumbnail_transients();

        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'privacy-lite-youtube-embeds',
                    'plye_notice' => 'clear',
                    'plye_deleted' => absint($deleted),
                ],
                admin_url('options-general.php')
            )
        );
        exit;
    }

    public function filter_render_block(string $block_content, array $block): string {
        if (is_admin() || empty($block_content) || empty($block['blockName']) || 'core/embed' !== $block['blockName']) {
            return $block_content;
        }

        $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];
        $provider = isset($attrs['providerNameSlug']) ? sanitize_key((string) $attrs['providerNameSlug']) : '';
        $url = isset($attrs['url']) ? (string) $attrs['url'] : '';

        if ('youtube' !== $provider && !$this->extract_video_id($url)) {
            return $block_content;
        }

        $video_id = $this->extract_video_id($url) ?: $this->extract_video_id($block_content);
        if (!$video_id) {
            return $block_content;
        }

        return $this->render_placeholder($video_id, $this->extract_title_from_html($block_content));
    }

    public function filter_oembed_html(string $html, string $url, array $attr, int $post_id): string {
        unset($attr, $post_id);

        if (is_admin() || 'all' !== $this->settings()['scope']) {
            return $html;
        }

        $video_id = $this->extract_video_id($url) ?: $this->extract_video_id($html);
        if (!$video_id) {
            return $html;
        }

        return $this->render_placeholder($video_id, $this->extract_title_from_html($html));
    }

    public function filter_content_iframes(string $content): string {
        if (is_admin() || is_feed() || 'all' !== $this->settings()['scope'] || false === stripos($content, 'youtu')) {
            return $content;
        }

        return preg_replace_callback(
            '#<iframe\b[^>]*\bsrc=["\']([^"\']*(?:youtube\.com|youtube-nocookie\.com|youtu\.be)[^"\']*)["\'][^>]*>\s*</iframe>#i',
            function (array $matches): string {
                $video_id = $this->extract_video_id($matches[1]);
                if (!$video_id) {
                    return $matches[0];
                }

                return $this->render_placeholder($video_id, $this->extract_title_from_html($matches[0]));
            },
            $content
        ) ?: $content;
    }

    public function prime_post_thumbnails(int $post_id, WP_Post $post, bool $update): void {
        unset($update);

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || empty($post->post_content)) {
            return;
        }

        $video_ids = $this->get_video_ids_for_post_content($post->post_content);
        foreach (array_slice($video_ids, 0, 10) as $video_id) {
            $this->get_local_thumbnail($video_id);
        }
    }

    private function render_admin_tool_notice(): void {
        if (empty($_GET['plye_notice'])) {
            return;
        }

        $notice = sanitize_key(wp_unslash((string) $_GET['plye_notice']));

        if ('scan' === $notice) {
            $posts = isset($_GET['plye_posts']) ? absint($_GET['plye_posts']) : 0;
            $videos = isset($_GET['plye_videos']) ? absint($_GET['plye_videos']) : 0;
            $existing = isset($_GET['plye_existing']) ? absint($_GET['plye_existing']) : 0;
            $generated = isset($_GET['plye_generated']) ? absint($_GET['plye_generated']) : 0;
            $failed = isset($_GET['plye_failed']) ? absint($_GET['plye_failed']) : 0;
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php
                    printf(
                        esc_html__('Scan complete. Posts scanned: %1$d. Videos found: %2$d. Existing thumbnails: %3$d. Generated: %4$d. Failed or unavailable: %5$d.', 'privacy-lite-youtube-embeds'),
                        $posts,
                        $videos,
                        $existing,
                        $generated,
                        $failed
                    );
                    ?>
                </p>
            </div>
            <?php
            return;
        }

        if ('clear' === $notice) {
            $deleted = isset($_GET['plye_deleted']) ? absint($_GET['plye_deleted']) : 0;
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php
                    printf(
                        esc_html__('Local thumbnail cache cleared. Deleted files: %d.', 'privacy-lite-youtube-embeds'),
                        $deleted
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }

    private function render_support_badge(): void {
        $support_url = $this->support_url();
        if (!$support_url) {
            return;
        }

        $logo_url = $this->support_logo_url();
        ?>
        <a href="<?php echo esc_url($support_url); ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex; align-items:center; gap:2px; padding:5px 15px 5px 0; border:1px solid #dcdcde; border-radius:12px; background:#fff; color:#1d2327; text-decoration:none; box-shadow:0 1px 2px rgba(0,0,0,.04); white-space:nowrap;">
            <?php if ($logo_url) : ?>
                <img src="<?php echo esc_url($logo_url); ?>" alt="" style="display:block; width:60px; height:60px; object-fit:contain; flex:0 0 auto;">
            <?php else : ?>
                <span aria-hidden="true" style="display:inline-flex; align-items:center; justify-content:center; width:45px; height:45px; border-radius:999px; background:#fff7ed; font-size:21px; line-height:1; flex:0 0 auto;">☕</span>
            <?php endif; ?>
            <span style="display:flex; flex-direction:column; line-height:1.2;">
                <span style="font-weight:600; color:#1d2327;"><?php echo esc_html__('Support development', 'privacy-lite-youtube-embeds'); ?></span>
                <span style="font-size:12px; color:#646970;"><?php echo esc_html__('Buy me a coffee on Ko-fi', 'privacy-lite-youtube-embeds'); ?></span>
            </span>
        </a>
        <?php
    }

    private function support_logo_url(): string {
        $filename = 'coffee-love-icon.svg';
        $path = plugin_dir_path(__FILE__) . 'assets/' . $filename;

        if (is_readable($path)) {
            return plugins_url('assets/' . $filename, __FILE__);
        }

        return '';
    }

    private function support_url(): string {
        $url = self::DEFAULT_SUPPORT_URL;
        if (defined('PLYE_SUPPORT_URL')) {
            $url = (string) PLYE_SUPPORT_URL;
        }
        $url = (string) apply_filters('plye_support_url', $url);
        $url = esc_url_raw($url, ['https']);

        return $url ?: '';
    }

    private function scan_content_for_thumbnails(int $limit): array {
        $result = [
            'posts_scanned' => 0,
            'videos_found' => 0,
            'existing' => 0,
            'generated' => 0,
            'failed' => 0,
        ];

        $query = new WP_Query([
            'post_type' => $this->public_post_types(),
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'modified',
            'order' => 'DESC',
            's' => 'youtu',
        ]);

        foreach ($query->posts as $post_id) {
            $post = get_post($post_id);
            if (!$post instanceof WP_Post || empty($post->post_content)) {
                continue;
            }

            $result['posts_scanned']++;
            $video_ids = $this->get_video_ids_for_post_content($post->post_content);
            $result['videos_found'] += count($video_ids);

            foreach ($video_ids as $video_id) {
                if ($this->thumbnail_exists($video_id)) {
                    $result['existing']++;
                    continue;
                }

                if ($this->get_local_thumbnail($video_id)) {
                    $result['generated']++;
                } else {
                    $result['failed']++;
                }
            }
        }

        wp_reset_postdata();

        return $result;
    }

    private function render_placeholder(string $video_id, string $title = ''): string {
        $settings = $this->settings();
        $thumb = $this->get_local_thumbnail($video_id);
        $label = $title ? sprintf(__('Play video: %s', 'privacy-lite-youtube-embeds'), $title) : __('Play YouTube video', 'privacy-lite-youtube-embeds');
        $classes = $thumb ? 'plye-video' : 'plye-video plye-video--no-thumb';

        ob_start();
        ?>
        <div class="<?php echo esc_attr($classes); ?>" data-plye-video-id="<?php echo esc_attr($video_id); ?>" data-plye-autoplay="<?php echo $settings['autoplay'] ? '1' : '0'; ?>">
            <button class="plye-video__button" type="button" aria-label="<?php echo esc_attr($label); ?>">
                <?php if ($thumb) : ?>
                    <img class="plye-video__thumb" src="<?php echo esc_url($thumb['url']); ?>" alt="" loading="lazy" decoding="async">
                <?php else : ?>
                    <span class="plye-video__fallback" aria-hidden="true"></span>
                <?php endif; ?>
                <span class="plye-video__overlay" aria-hidden="true"></span>
                <span class="plye-video__play" aria-hidden="true"></span>
                <?php if (!empty($settings['show_consent_text']) && '' !== trim($settings['consent_text'])) : ?>
                    <span class="plye-video__consent"><?php echo esc_html($settings['consent_text']); ?></span>
                <?php endif; ?>
            </button>
        </div>
        <?php

        return trim((string) ob_get_clean());
    }

    private function get_local_thumbnail(string $video_id): ?array {
        $file = $this->thumbnail_file($video_id);
        if (!$file) {
            return null;
        }

        if (is_readable($file['path'])) {
            return $file;
        }

        $video_id = $this->sanitize_video_id($video_id);
        if (get_transient($this->failed_thumbnail_transient_key($video_id))) {
            return null;
        }

        $dir = dirname($file['path']);
        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            return null;
        }

        if (!$this->download_thumbnail($video_id, $file['path']) || !is_readable($file['path'])) {
            set_transient($this->failed_thumbnail_transient_key($video_id), 1, self::FAILED_THUMB_TTL);
            return null;
        }

        delete_transient($this->failed_thumbnail_transient_key($video_id));

        return $file;
    }

    private function download_thumbnail(string $video_id, string $destination): bool {
        $candidates = ['maxresdefault.jpg', 'sddefault.jpg', 'hqdefault.jpg'];

        foreach ($candidates as $candidate) {
            $url = sprintf('https://img.youtube.com/vi/%s/%s', rawurlencode($video_id), $candidate);
            $response = wp_safe_remote_get(
                $url,
                [
                    'timeout' => 8,
                    'redirection' => 3,
                    'user-agent' => 'Privacy Lite YouTube Embeds/' . self::VERSION . '; ' . home_url('/'),
                ]
            );

            if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
                continue;
            }

            $content_type = (string) wp_remote_retrieve_header($response, 'content-type');
            $body = wp_remote_retrieve_body($response);
            if (false === stripos($content_type, 'image/') || strlen($body) < 1000) {
                continue;
            }

            $size = function_exists('getimagesizefromstring') ? @getimagesizefromstring($body) : false;
            if (!$size || empty($size[0]) || (int) $size[0] < 320) {
                continue;
            }

            if (false !== file_put_contents($destination, $body, LOCK_EX)) {
                return true;
            }
        }

        return false;
    }

    private function get_video_ids_for_post_content(string $content): array {
        if ('gutenberg' === $this->settings()['scope'] && function_exists('parse_blocks')) {
            return $this->get_video_ids_from_blocks(parse_blocks($content));
        }

        return $this->extract_video_ids($content);
    }

    private function get_video_ids_from_blocks(array $blocks): array {
        $video_ids = [];

        foreach ($blocks as $block) {
            if (!empty($block['blockName']) && 'core/embed' === $block['blockName']) {
                $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];
                $url = isset($attrs['url']) ? (string) $attrs['url'] : '';
                $provider = isset($attrs['providerNameSlug']) ? sanitize_key((string) $attrs['providerNameSlug']) : '';
                $video_id = $this->extract_video_id($url);

                if ($video_id && ('youtube' === $provider || $video_id)) {
                    $video_ids[] = $video_id;
                }
            }

            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $video_ids = array_merge($video_ids, $this->get_video_ids_from_blocks($block['innerBlocks']));
            }
        }

        return array_values(array_unique(array_filter($video_ids)));
    }

    private function extract_video_ids(string $value): array {
        if ('' === $value || false === stripos($value, 'youtu')) {
            return [];
        }

        $value = html_entity_decode($value, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
        $video_ids = [];
        $patterns = [
            '#youtu\.be/([A-Za-z0-9_-]{6,20})#i',
            '#youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{6,20})#i',
            '#youtube(?:-nocookie)?\.com/shorts/([A-Za-z0-9_-]{6,20})#i',
            '#[?&]v=([A-Za-z0-9_-]{6,20})#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $value, $matches)) {
                foreach ($matches[1] as $match) {
                    $video_id = $this->sanitize_video_id($match);
                    if ($video_id) {
                        $video_ids[] = $video_id;
                    }
                }
            }
        }

        return array_values(array_unique($video_ids));
    }

    private function extract_video_id(string $value): string {
        $video_ids = $this->extract_video_ids($value);
        return $video_ids[0] ?? '';
    }

    private function sanitize_video_id(string $video_id): string {
        $video_id = trim($video_id);
        return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $video_id) ? $video_id : '';
    }

    private function thumbnail_exists(string $video_id): bool {
        $file = $this->thumbnail_file($video_id);
        return null !== $file && is_readable($file['path']);
    }

    private function thumbnail_file(string $video_id): ?array {
        $video_id = $this->sanitize_video_id($video_id);
        if (!$video_id) {
            return null;
        }

        $upload_dir = wp_upload_dir();
        if (empty($upload_dir['basedir']) || empty($upload_dir['baseurl'])) {
            return null;
        }

        $filename = $video_id . '.jpg';

        return [
            'path' => trailingslashit($upload_dir['basedir']) . self::THUMB_DIR . '/' . $filename,
            'url' => trailingslashit($upload_dir['baseurl']) . self::THUMB_DIR . '/' . $filename,
        ];
    }

    private function clear_thumbnail_cache(): int {
        $upload_dir = wp_upload_dir();
        if (empty($upload_dir['basedir'])) {
            return 0;
        }

        $dir = trailingslashit($upload_dir['basedir']) . self::THUMB_DIR;
        $files = is_dir($dir) && is_readable($dir) ? glob(trailingslashit($dir) . '*.jpg') : [];
        $deleted = 0;

        foreach (is_array($files) ? $files : [] as $file) {
            if (is_file($file) && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function clear_failed_thumbnail_transients(): void {
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

    private function public_post_types(): array {
        $post_types = get_post_types(['public' => true], 'names');
        unset($post_types['attachment']);

        return array_values($post_types);
    }

    private function failed_thumbnail_transient_key(string $video_id): string {
        return 'plye_thumb_fail_' . md5($video_id);
    }

    private function extract_title_from_html(string $html): string {
        if (preg_match('#\btitle=["\']([^"\']+)["\']#i', $html, $matches)) {
            return sanitize_text_field(wp_strip_all_tags(html_entity_decode($matches[1], ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8')));
        }

        if (preg_match('#<figcaption[^>]*>(.*?)</figcaption>#is', $html, $matches)) {
            return sanitize_text_field(wp_strip_all_tags($matches[1]));
        }

        return '';
    }

    private function settings(): array {
        return wp_parse_args((array) get_option(self::OPTION_NAME, []), $this->default_settings());
    }

    private function default_settings(): array {
        return [
            'scope' => 'all',
            'show_consent_text' => true,
            'consent_text' => __('The video is loaded from YouTube only after your click.', 'privacy-lite-youtube-embeds'),
            'autoplay' => true,
        ];
    }
}

register_activation_hook(__FILE__, ['Privacy_Lite_YouTube_Embeds', 'activate']);
Privacy_Lite_YouTube_Embeds::instance();
