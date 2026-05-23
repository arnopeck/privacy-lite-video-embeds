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
    private const FAILED_KEYS_OPTION = 'plye_failed_thumbnail_keys';
    private const THUMB_DIR = 'privacy-lite-youtube-embeds';
    private const FAILED_THUMB_TTL = 12 * HOUR_IN_SECONDS;
    private const MAX_SCAN_POSTS = 50;
    private const DEFAULT_SUPPORT_URL = 'https://ko-fi.com/luminescenza';
    private const DEFAULT_PLAY_BUTTON_COLOR = '#ff0000';

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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
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

        wp_enqueue_style('privacy-lite-youtube-embeds', plugins_url('assets/privacy-lite-youtube-embeds.css', __FILE__), [], self::VERSION);
        wp_add_inline_style(
            'privacy-lite-youtube-embeds',
            '.plye-video{--plye-play-color:' . esc_html($this->settings()['play_button_color']) . ';}'
        );
        wp_enqueue_script('privacy-lite-youtube-embeds', plugins_url('assets/privacy-lite-youtube-embeds.js', __FILE__), [], self::VERSION, true);
    }

    public function enqueue_admin_assets(string $hook_suffix): void {
        if ('settings_page_privacy-lite-youtube-embeds' !== $hook_suffix) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script(
            'wp-color-picker',
            'jQuery(function($){$(\'.plye-color-picker\').wpColorPicker();});'
        );
    }

    public function add_plugin_action_links(array $links): array {
        array_unshift(
            $links,
            '<a href="' . esc_url(admin_url('options-general.php?page=privacy-lite-youtube-embeds')) . '">' . esc_html__('Settings', 'privacy-lite-youtube-embeds') . '</a>'
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
        add_settings_field('play_button_color', __('Play button color', 'privacy-lite-youtube-embeds'), [$this, 'render_play_button_color_field'], 'plye_settings', 'plye_main_section');
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
                            /* translators: %d: maximum number of published public posts/pages scanned per run. */
                            esc_html__('Scans up to %d published public posts/pages per run and downloads missing local thumbnails.', 'privacy-lite-youtube-embeds'),
                            esc_html((string) self::MAX_SCAN_POSTS)
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

    public function render_play_button_color_field(): void {
        $settings = $this->settings();
        ?>
        <input type="text" class="plye-color-picker" name="<?php echo esc_attr(self::OPTION_NAME); ?>[play_button_color]" value="<?php echo esc_attr($settings['play_button_color']); ?>" data-default-color="<?php echo esc_attr(self::DEFAULT_PLAY_BUTTON_COLOR); ?>">
        <p class="description"><?php echo esc_html__('Choose the overlay play button color shown before the video is loaded.', 'privacy-lite-youtube-embeds'); ?></p>
        <?php
    }

    public function sanitize_settings($input): array {
        $defaults = $this->default_settings();
        $input = is_array($input) ? $input : [];

        $scope = isset($input['scope']) ? sanitize_key((string) $input['scope']) : $defaults['scope'];
        if (!in_array($scope, ['gutenberg', 'all'], true)) {
            $scope = $defaults['scope'];
        }

        $play_button_color = isset($input['play_button_color']) ? sanitize_hex_color((string) $input['play_button_color']) : '';

        return [
            'scope' => $scope,
            'show_consent_text' => !empty($input['show_consent_text']),
            'consent_text' => isset($input['consent_text']) ? sanitize_textarea_field((string) $input['consent_text']) : $defaults['consent_text'],
            'autoplay' => !empty($input['autoplay']),
            'play_button_color' => $play_button_color ?: $defaults['play_button_color'],
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

        foreach (array_slice($this->get_video_ids_for_post_content($post->post_content), 0, 10) as $video_id) {
            $this->get_local_thumbnail($video_id);
        }
    }

    private function render_admin_tool_notice(): void {
        if (empty($_GET['plye_notice'])) {
            return;
        }

    