<?php
namespace UserPortal\Core;

if (!defined('ABSPATH')) exit;

class Auth {

    public static function init() {
        add_action('init', [__CLASS__, 'handle_login']);
        add_action('init', [__CLASS__, 'handle_logout']);

        // AJAX handlers
        add_action('wp_ajax_nopriv_up_ajax_login', [__CLASS__, 'ajax_login']);
        add_action('wp_ajax_up_ajax_login', [__CLASS__, 'ajax_login']);

        // Hook do zapisywania historii logowań
        add_action('wp_login', [__CLASS__, 'log_user_login'], 10, 2);
        add_action('wp_login_failed', [__CLASS__, 'log_failed_login']);
    }

    /**
     * Obsługa logowania
     */
    public static function handle_login() {
        if (!isset($_POST['up_login_submit'])) {
            return;
        }

        // Weryfikacja nonce
        if (!isset($_POST['up_login_nonce']) || !wp_verify_nonce($_POST['up_login_nonce'], 'up_login_action')) {
            return;
        }

        $username = sanitize_user($_POST['up_username'] ?? '');
        $password = $_POST['up_password'] ?? '';
        $remember = isset($_POST['up_remember']);

        if (empty($username) || empty($password)) {
            self::set_login_error('Wypełnij wszystkie pola.');
            return;
        }

        $creds = [
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        ];

        $user = wp_signon($creds, is_ssl());

        if (is_wp_error($user)) {
            self::set_login_error('Nieprawidłowa nazwa użytkownika lub hasło.');
            return;
        }

        // Przekieruj do strony głównej po zalogowaniu
        wp_safe_redirect(home_url('/'));
        exit;
    }

    /**
     * Obsługa wylogowania
     */
    public static function handle_logout() {
        if (!isset($_GET['up_logout'])) {
            return;
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'up_logout')) {
            return;
        }

        wp_logout();
        wp_safe_redirect(home_url('/'));
        exit;
    }

    /**
     * Ustaw błąd logowania w sesji
     */
    private static function set_login_error($message) {
        if (!session_id()) {
            session_start();
        }
        $_SESSION['up_login_error'] = $message;
    }

    /**
     * Pobierz i usuń błąd logowania
     */
    public static function get_login_error() {
        if (!session_id()) {
            session_start();
        }

        if (isset($_SESSION['up_login_error'])) {
            $error = $_SESSION['up_login_error'];
            unset($_SESSION['up_login_error']);
            return $error;
        }

        return '';
    }

    /**
     * Renderuj formularz logowania
     */
    public static function render_login_form() {
        $error = self::get_login_error();
        include UP_PATH . 'templates/login-form.php';
    }

    /**
     * URL wylogowania
     */
    public static function get_logout_url() {
        return wp_nonce_url(home_url('/?up_logout=1'), 'up_logout');
    }

    /**
     * AJAX handler for login
     */
    public static function ajax_login() {
        // Weryfikacja nonce
        if (!isset($_POST['up_login_nonce']) || !wp_verify_nonce($_POST['up_login_nonce'], 'up_login_action')) {
            wp_send_json_error([
                'message' => 'Nieprawidłowe żądanie.'
            ]);
        }

        $username = sanitize_user($_POST['up_username'] ?? '');
        $password = $_POST['up_password'] ?? '';
        $remember = isset($_POST['up_remember']);

        if (empty($username) || empty($password)) {
            wp_send_json_error([
                'message' => 'Wypełnij wszystkie pola.'
            ]);
        }

        $creds = [
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        ];

        $user = wp_signon($creds, is_ssl());

        if (is_wp_error($user)) {
            wp_send_json_error([
                'message' => 'Nieprawidłowa nazwa użytkownika lub hasło.'
            ]);
        }

        // Sukces - zwróć pozytywną odpowiedź
        wp_send_json_success([
            'message' => 'Zalogowano pomyślnie!',
            'redirect' => home_url('/')
        ]);
    }

    /**
     * Zapisz udane logowanie do historii
     */
    public static function log_user_login($user_login, $user) {
        self::save_login_attempt($user->ID, true);
    }

    /**
     * Zapisz nieudane logowanie do historii
     */
    public static function log_failed_login($username) {
        // Spróbuj znaleźć użytkownika po nazwie lub emailu
        $user = get_user_by('login', $username);
        if (!$user) {
            $user = get_user_by('email', $username);
        }

        // Jeśli znaleziono użytkownika, zapisz nieudaną próbę
        if ($user) {
            self::save_login_attempt($user->ID, false);
        }
    }

    /**
     * Zapisz próbę logowania (udaną lub nieudaną)
     */
    private static function save_login_attempt($user_id, $success = true) {
        // Pobierz aktualną historię
        $login_history = get_user_meta($user_id, 'kp_login_history', true);
        if (!is_array($login_history)) {
            $login_history = [];
        }

        // Przygotuj nowy wpis
        $new_entry = [
            'date' => current_time('mysql'),
            'ip' => self::get_user_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : 'Nieznane',
            'success' => $success
        ];

        // Dodaj na początek tablicy (najnowsze pierwsze)
        array_unshift($login_history, $new_entry);

        // Ogranicz do ostatnich 50 wpisów
        $login_history = array_slice($login_history, 0, 50);

        // Zapisz do user meta
        update_user_meta($user_id, 'kp_login_history', $login_history);
    }

    /**
     * Pobierz adres IP użytkownika
     */
    private static function get_user_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return sanitize_text_field($_SERVER[$key]);
            }
        }

        return 'Nieznane';
    }
}
