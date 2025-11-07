<?php
namespace UserPortal\Core;

if (!defined('ABSPATH')) exit;

class Assets {

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Załaduj tylko nasze style i skrypty
     */
    public static function enqueue_assets() {
        // Ładuj na stronie głównej (gdzie Frontend przejmuje kontrolę)
        if (is_front_page() || is_home()) {
            // Na stronie głównej zawsze ładuj
        } else if (is_singular()) {
            // Na innych stronach sprawdź shortcode
            global $post;
            if (!$post || !has_shortcode($post->post_content, 'user_portal')) {
                return;
            }
        } else {
            // Inne typy stron - nie ładuj
            return;
        }

        // Font Awesome
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
            [],
            '6.4.0'
        );

        // Główny CSS
        wp_enqueue_style(
            'user-portal-main',
            UP_URL . 'assets/css/main.css',
            ['font-awesome'],
            UP_VERSION
        );

        // Główny JS
        wp_enqueue_script(
            'user-portal-main',
            UP_URL . 'assets/js/main.js',
            [],
            UP_VERSION,
            true
        );

        // Zarejestruj calendar.js (nie ładuj od razu, będzie ładowany on-demand)
        wp_register_script(
            'user-portal-calendar',
            UP_URL . 'assets/js/calendar.js',
            [],
            UP_VERSION,
            true
        );

        // Zarejestruj my-reports.js (nie ładuj od razu, będzie ładowany on-demand)
        wp_register_script(
            'user-portal-my-reports',
            UP_URL . 'assets/js/my-reports.js',
            [],
            UP_VERSION,
            true
        );

        // Zarejestruj stats.js (nie ładuj od razu, będzie ładowany on-demand)
        wp_register_script(
            'user-portal-stats',
            UP_URL . 'assets/js/stats.js',
            [],
            UP_VERSION,
            true
        );

        // Zarejestruj profile.js (nie ładuj od razu, będzie ładowany on-demand)
        wp_register_script(
            'user-portal-profile',
            UP_URL . 'assets/js/profile.js',
            [],
            UP_VERSION,
            true
        );

        // Localize script - przekaż dane do JS
        if (is_user_logged_in()) {
            wp_localize_script('user-portal-main', 'UPPANEL', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('up_load_view'),
                'report_nonce' => wp_create_nonce('up_report_action'),
                'avatar_nonce' => wp_create_nonce('up_avatar_action'),
                'profile_nonce' => wp_create_nonce('up_avatar_action'),
                'calendar_js_url' => UP_URL . 'assets/js/calendar.js',
                'my_reports_js_url' => UP_URL . 'assets/js/my-reports.js',
                'stats_js_url' => UP_URL . 'assets/js/stats.js',
                'profile_js_url' => UP_URL . 'assets/js/profile.js',
            ]);
        }
    }
}
