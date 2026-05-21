<?php
/**
 * Plugin Name: Privacy Lite YouTube Embeds
 * Description: Replaces YouTube embeds with fast, privacy-friendly local placeholders and loads youtube-nocookie only after user interaction.
 * Version: 0.1.0
 * Author: Arno Peck
 * Author URI: https://github.com/arnopeck
 * Text Domain: privacy-lite-youtube-embeds
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Privacy_Lite_YouTube_Embeds {
    private const VERSION = '0.1.0';
    private const OPTION_NAME = 'plye_settings';
    private const THUMB_DIR = 'privacy-lite-youtube-embeds';

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
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        add_filter('render_block', [$this, 'filter_render_block'], 20, 2);
        add_filter('embed_oembed_html', [$this, 'filter_oembed_html'], 20, 4);
        add_filter('the_content', [$this, 'filter_content_iframes'], 20);
    }

    public function load_textdomain(): void {
        load_plugin_textdomain('privacy-lite-youtube-embeds', false, dirname(plugin_basename(__FILE__)) . '/languages');
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
            <h1><?php echo esc_html__('Privacy Lite YouTube Embeds', 'privacy-lite-youtube-embeds'); ?></h1>
            <p><?php echo esc_html__('Before click, the frontend uses only local HTML, CSS, JavaScript and locally cached thumbnails. YouTube is loaded from youtube-nocookie.com only after interaction.', 'privacy-lite-youtube-embeds'); ?></p>
            <form method="post" action="options.php">
                <?php
                settings_fields('plye_settings_group');
                do_settings_sections('plye_settings');
                submit_button();
                ?>
            </form>
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

    private function render_placeholder(string $video_id, string $title = ''): string {
        $settings = $this->settings();
        $thumb = $this->get_local_thumbnail($video_id);
        $label = $title ? sprintf(__('Play video: %s', 'privacy-lite-youtube-embeds'), $title) : __('Play YouTube video', 'privacy-lite-youtube-embeds');
        $classes = 'plye-video';
        if (!$thumb) {
            $classes .= ' plye-video--no-thumb';
        }

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
        $video_id = $this->sanitize_video_id($video_id);
        if (!$video_id) {
            return null;
        }

        $upload_dir = wp_upload_dir();
        if (empty($upload_dir['basedir']) || empty($upload_dir['baseurl'])) {
            return null;
        }

        $dir = trailingslashit($upload_dir['basedir']) . self::THUMB_DIR;
        $url_base = trailingslashit($upload_dir['baseurl']) . self::THUMB_DIR;
        $filename = $video_id . '.jpg';
        $path = trailingslashit($dir) . $filename;
        $url = trailingslashit($url_base) . $filename;

        if (is_readable($path)) {
            return ['path' => $path, 'url' => $url];
        }

        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            return null;
        }

        $downloaded = $this->download_thumbnail($video_id, $path);
        if (!$downloaded || !is_readable($path)) {
            return null;
        }

        return ['path' => $path, 'url' => $url];
    }

    private function download_thumbnail(string $video_id, string $destination): bool {
        $candidates = [
            'maxresdefault.jpg',
            'sddefault.jpg',
            'hqdefault.jpg',
        ];

        foreach ($candidates as $candidate) {
            $url = sprintf('https://img.youtube.com/vi/%s/%s', rawurlencode($video_id), $candidate);
            $response = wp_remote_get($url, [
                'timeout' => 8,
                'redirection' => 3,
                'user-agent' => 'Privacy Lite YouTube Embeds/' . self::VERSION . '; ' . home_url('/'),
            ]);

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

            $written = file_put_contents($destination, $body, LOCK_EX);
            if (false !== $written) {
                return true;
            }
        }

        return false;
    }

    private function extract_video_id(string $value): string {
        if ('' === $value) {
            return '';
        }

        $value = html_entity_decode($value, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');

        $patterns = [
            '#youtu\.be/([A-Za-z0-9_-]{6,20})#i',
            '#youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{6,20})#i',
            '#youtube(?:-nocookie)?\.com/shorts/([A-Za-z0-9_-]{6,20})#i',
            '#youtube(?:-nocookie)?\.com/(?:watch|.*?[?&]v=)[^\s"\']*?[?&]v=([A-Za-z0-9_-]{6,20})#i',
            '#[?&]v=([A-Za-z0-9_-]{6,20})#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value, $matches)) {
                return $this->sanitize_video_id($matches[1]);
            }
        }

        return '';
    }

    private function sanitize_video_id(string $video_id): string {
        $video_id = trim($video_id);
        if (!preg_match('/^[A-Za-z0-9_-]{6,20}$/', $video_id)) {
            return '';
        }

        return $video_id;
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
