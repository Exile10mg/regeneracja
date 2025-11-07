<?php
namespace UserPortal\Core;

if (!defined('ABSPATH')) exit;

class Frontend {

    public static function init() {
        // Hook na template_redirect z wysokim priorytetem
        add_action('template_redirect', [__CLASS__, 'handle_homepage'], 1);

        // Wyłącz wszystkie style i skrypty na stronie głównej
        add_action('wp_enqueue_scripts', [__CLASS__, 'dequeue_unnecessary_assets'], 999);
    }

    /**
     * Przejmij kontrolę nad stroną główną
     */
    public static function handle_homepage() {
        if (!is_front_page() && !is_home()) {
            return;
        }

        // Usuń wszystkie akcje z wp_head i wp_footer, które nie są krytyczne
        remove_all_actions('wp_head', 10);
        remove_all_actions('wp_footer', 10);

        // Dodaj z powrotem tylko niezbędne akcje
        add_action('wp_head', 'wp_enqueue_scripts', 1);
        add_action('wp_head', 'wp_print_styles', 8);
        add_action('wp_head', 'wp_print_head_scripts', 9);
        add_action('wp_footer', 'wp_print_footer_scripts', 20);

        // Wyświetl nasz custom template
        self::render_page();
        exit;
    }

    /**
     * Wyłącz niepotrzebne style i skrypty na stronie głównej
     */
    public static function dequeue_unnecessary_assets() {
        if (!is_front_page() && !is_home()) {
            return;
        }

        global $wp_styles, $wp_scripts;

        // Lista dozwolonych handlei (tylko nasze)
        $allowed_styles = ['user-portal-main'];
        $allowed_scripts = ['user-portal-main'];

        // Usuń wszystkie style oprócz naszych
        if (isset($wp_styles->queue)) {
            foreach ($wp_styles->queue as $handle) {
                if (!in_array($handle, $allowed_styles)) {
                    wp_dequeue_style($handle);
                }
            }
        }

        // Usuń wszystkie skrypty oprócz naszych
        if (isset($wp_scripts->queue)) {
            foreach ($wp_scripts->queue as $handle) {
                if (!in_array($handle, $allowed_scripts)) {
                    wp_dequeue_script($handle);
                }
            }
        }

        // Wyłącz Elementor na stronie głównej
        remove_action('wp_head', 'elementor_frontend_stylesheet');
        remove_action('wp_footer', 'elementor_frontend_scripts');

        // Wyłącz emoji
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');

        // Wyłącz embed
        wp_deregister_script('wp-embed');
    }

    /**
     * Renderuj stronę główną
     */
    private static function render_page() {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php bloginfo('name'); ?> - Portal Użytkownika</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
            <?php wp_head(); ?>
        </head>
        <body class="user-portal<?php echo !is_user_logged_in() ? ' kp-login-page' : ''; ?>">
            <div class="up-container">
                <?php
                if (is_user_logged_in()) {
                    Dashboard::render();
                } else {
                    Auth::render_login_form();
                }
                ?>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }
}
