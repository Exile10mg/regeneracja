<?php
/**
 * Plugin Name: User Portal
 * Plugin URI: https://example.com
 * Description: Czysty portal użytkownika na stronie głównej - logowanie i dashboard
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: user-portal
 * Domain Path: /languages
 */

namespace UserPortal;

if (!defined('ABSPATH')) exit;

// Plugin constants
define('UP_VERSION', '1.0.0');
define('UP_PATH', plugin_dir_path(__FILE__));
define('UP_URL', plugin_dir_url(__FILE__));

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'UserPortal\\Core\\';
    $base_dir = UP_PATH . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . strtolower($relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize plugin
function init() {
    Core\Frontend::init();
    Core\Auth::init();
    Core\Assets::init();
    Core\Dashboard::init();
}
add_action('plugins_loaded', __NAMESPACE__ . '\\init');

// Activation hook
register_activation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});
