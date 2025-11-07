<?php
namespace UserPortal\Core;

if (!defined('ABSPATH')) exit;

class Dashboard {

    public static function init() {
        // AJAX handler for loading views
        add_action('wp_ajax_up_load_view', [__CLASS__, 'ajax_load_view']);

        // AJAX handler for saving reports
        add_action('wp_ajax_up_save_report', [__CLASS__, 'ajax_save_report']);

        // AJAX handler for getting reports list (my-reports view)
        add_action('wp_ajax_up_get_reports', [__CLASS__, 'ajax_get_reports']);

        // AJAX handler for uploading avatar
        add_action('wp_ajax_up_upload_avatar', [__CLASS__, 'ajax_upload_avatar']);

        // AJAX handlers for profile management
        add_action('wp_ajax_up_update_profile', [__CLASS__, 'ajax_update_profile']);
        add_action('wp_ajax_up_change_password', [__CLASS__, 'ajax_change_password']);
        add_action('wp_ajax_up_get_login_history', [__CLASS__, 'ajax_get_login_history']);
    }


    /**
     * Renderuj dashboard dla zalogowanego użytkownika
     */
    public static function render() {
        if (!is_user_logged_in()) {
            return;
        }

        include UP_PATH . 'templates/dashboard.php';
    }

    /**
     * AJAX: Ładowanie widoków (dzisiejszy raport, kalendarz, etc.)
     */
    public static function ajax_load_view() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Musisz być zalogowany.']);
        }

        // Używamy GET bo JS wysyła query params
        check_ajax_referer('up_load_view', 'nonce');

        $view = sanitize_key($_GET['view'] ?? 'today');
        $current_user = wp_get_current_user();

        // Zabezpieczenie: Sprawdź uprawnienia dla widoku "team"
        if ($view === 'team' && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nie masz uprawnień do przeglądania raportów zespołu.']);
        }

        $view_file = UP_PATH . 'views/' . $view . '.php';

        if (!file_exists($view_file)) {
            wp_send_json_error(['message' => 'Widok nie istnieje.']);
        }

        ob_start();
        include $view_file;
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * AJAX: Zapis raportu (szkic/złożenie/usunięcie)
     */
    public static function ajax_save_report() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Musisz być zalogowany.']);
        }

        check_ajax_referer('up_report_action', 'nonce');

        $user_id = get_current_user_id();
        $mode = sanitize_key($_POST['mode'] ?? 'draft');
        $date = sanitize_text_field($_POST['up_date'] ?? date('Y-m-d'));

        // Pobierz wszystkie raporty użytkownika - UŻYWAMY kp_reports dla kompatybilności
        $all_reports = get_user_meta($user_id, 'kp_reports', true);
        if (!is_array($all_reports)) {
            $all_reports = [];
        }

        if ($mode === 'delete') {
            // Usuń raport
            unset($all_reports[$date]);
            update_user_meta($user_id, 'kp_reports', $all_reports);
            wp_send_json_success(['message' => 'Raport usunięty.', 'status' => '']);
        }

        // Zapisz raport (szkic lub złożony)
        $tasks_raw = $_POST['up_tasks'] ?? [];
        $note = sanitize_textarea_field($_POST['up_note'] ?? '');

        // Sanitize nested tasks structure
        $tasks = [];
        if (is_array($tasks_raw)) {
            foreach ($tasks_raw as $cat_key => $cat_tasks) {
                $cat_key_clean = sanitize_key($cat_key);
                $tasks[$cat_key_clean] = [];
                
                if (is_array($cat_tasks)) {
                    foreach ($cat_tasks as $task_key => $task_data) {
                        $task_key_clean = sanitize_key($task_key);
                        $tasks[$cat_key_clean][$task_key_clean] = [
                            'qty' => isset($task_data['qty']) ? absint($task_data['qty']) : 0,
                            'time' => isset($task_data['time']) ? sanitize_text_field($task_data['time']) : '',
                            'note' => isset($task_data['note']) ? sanitize_textarea_field($task_data['note']) : '',
                        ];
                    }
                }
            }
        }

        $report = [
            'date' => $date,
            'status' => $mode === 'submit' ? 'submitted' : 'draft',
            'tasks' => $tasks,
            'note' => $note,
            'time' => current_time('mysql'),
        ];

        $all_reports[$date] = $report;
        update_user_meta($user_id, 'kp_reports', $all_reports);

        wp_send_json_success([
            'message' => $mode === 'submit' ? 'Raport złożony pomyślnie!' : 'Szkic zapisany.',
            'status' => $report['status']
        ]);
    }

    /**
     * AJAX: Pobierz listę raportów z paginacją i filtrami
     */
    public static function ajax_get_reports() {
        error_log('AJAX up_get_reports called');
        
        if (!is_user_logged_in()) {
            error_log('up_get_reports: User not logged in');
            wp_send_json_error(['message' => 'Musisz być zalogowany.']);
        }

        error_log('up_get_reports: User logged in, checking nonce');
        
        // Sprawdź nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'up_report_action')) {
            error_log('up_get_reports: Nonce verification failed. Nonce: ' . ($_POST['nonce'] ?? 'NONE'));
            wp_send_json_error(['message' => 'Błąd weryfikacji bezpieczeństwa.']);
        }
        
        error_log('up_get_reports: Nonce OK, fetching reports');

        $user_id = get_current_user_id();
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 15;
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';

        // Pobierz wszystkie raporty użytkownika
        $all_reports = get_user_meta($user_id, 'kp_reports', true);
        if (!is_array($all_reports)) {
            $all_reports = [];
        }

        // Filtrowanie
        $filtered_reports = [];
        foreach ($all_reports as $date => $report) {
            // Filtr daty od
            if ($date_from && $date < $date_from) {
                continue;
            }

            // Filtr daty do
            if ($date_to && $date > $date_to) {
                continue;
            }

            // Filtr statusu
            if ($status && (!isset($report['status']) || $report['status'] !== $status)) {
                continue;
            }

            $filtered_reports[$date] = $report;
        }

        // Sortuj po dacie malejąco (najnowsze pierwsze)
        krsort($filtered_reports);

        // Paginacja
        $total_reports = count($filtered_reports);
        $total_pages = max(1, ceil($total_reports / $per_page));
        $page = min($page, $total_pages); // Nie pozwalaj na strony większe niż max
        $offset = ($page - 1) * $per_page;

        $paginated_reports = array_slice($filtered_reports, $offset, $per_page, true);

        // Przygotuj dane do wysłania
        $reports_data = [];
        foreach ($paginated_reports as $date => $report) {
            $reports_data[] = [
                'date' => $date,
                'status' => $report['status'] ?? '',
                'tasks' => $report['tasks'] ?? [],
                'note' => $report['note'] ?? '',
                'time' => $report['time'] ?? ''
            ];
        }

        wp_send_json_success([
            'reports' => $reports_data,
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_reports' => $total_reports
        ]);
    }

    /**
     * AJAX: Upload avatara
     */
    public static function ajax_upload_avatar() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Musisz być zalogowany.']);
        }

        // Sprawdź nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'up_avatar_action')) {
            wp_send_json_error(['message' => 'Błąd weryfikacji bezpieczeństwa.']);
        }

        if (empty($_FILES['avatar'])) {
            wp_send_json_error(['message' => 'Nie przesłano pliku.']);
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $file = $_FILES['avatar'];
        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            wp_send_json_error(['message' => $upload['error']]);
        }

        $attachment_id = wp_insert_attachment([
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_file_name($file['name']),
            'post_content' => '',
            'post_status' => 'inherit'
        ], $upload['file']);

        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));

        // Używamy kp_avatar_id dla kompatybilności ze starą wtyczką
        update_user_meta(get_current_user_id(), 'kp_avatar_id', $attachment_id);

        $avatar_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');

        wp_send_json_success([
            'message' => 'Avatar zaktualizowany!',
            'avatar_url' => $avatar_url
        ]);
    }

    /**
     * AJAX: Aktualizacja profilu użytkownika
     */
    public static function ajax_update_profile() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Musisz być zalogowany.']);
        }

        // Sprawdź nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'up_avatar_action')) {
            wp_send_json_error(['message' => 'Błąd weryfikacji bezpieczeństwa.']);
        }

        $user_id = get_current_user_id();
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $display_name = sanitize_text_field($_POST['display_name'] ?? '');
        $user_email = sanitize_email($_POST['user_email'] ?? '');

        // Walidacja email
        if (!is_email($user_email)) {
            wp_send_json_error(['message' => 'Nieprawidłowy adres e-mail.']);
        }

        // Sprawdź czy email nie jest już zajęty przez innego użytkownika
        $email_exists = email_exists($user_email);
        if ($email_exists && $email_exists != $user_id) {
            wp_send_json_error(['message' => 'Ten adres e-mail jest już używany przez inne konto.']);
        }

        // Aktualizuj dane użytkownika
        $updated = wp_update_user([
            'ID' => $user_id,
            'user_email' => $user_email,
            'display_name' => $display_name
        ]);

        if (is_wp_error($updated)) {
            wp_send_json_error(['message' => 'Nie udało się zaktualizować profilu.']);
        }

        // Aktualizuj meta pola
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);

        wp_send_json_success([
            'message' => 'Profil został zaktualizowany!'
        ]);
    }

    /**
     * AJAX: Zmiana hasła
     */
    public static function ajax_change_password() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Musisz być zalogowany.']);
        }

        // Sprawdź nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'up_avatar_action')) {
            wp_send_json_error(['message' => 'Błąd weryfikacji bezpieczeństwa.']);
        }

        $user_id = get_current_user_id();
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Walidacja
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            wp_send_json_error(['message' => 'Wszystkie pola są wymagane.']);
        }

        if ($new_password !== $confirm_password) {
            wp_send_json_error(['message' => 'Nowe hasła nie są identyczne.']);
        }

        if (strlen($new_password) < 8) {
            wp_send_json_error(['message' => 'Nowe hasło musi mieć co najmniej 8 znaków.']);
        }

        // Sprawdź obecne hasło
        $user = get_user_by('id', $user_id);
        if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
            wp_send_json_error(['message' => 'Obecne hasło jest nieprawidłowe.']);
        }

        // Zmień hasło
        wp_set_password($new_password, $user_id);

        // Wyloguj ze wszystkich innych sesji
        wp_destroy_other_sessions();

        wp_send_json_success([
            'message' => 'Hasło zostało zmienione pomyślnie!'
        ]);
    }

    /**
     * AJAX: Pobierz historię logowań
     */
    public static function ajax_get_login_history() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Musisz być zalogowany.']);
        }

        // Sprawdź nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'up_avatar_action')) {
            wp_send_json_error(['message' => 'Błąd weryfikacji bezpieczeństwa.']);
        }

        $user_id = get_current_user_id();
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 5;

        // Pobierz historię logowań z user meta
        $login_history = get_user_meta($user_id, 'kp_login_history', true);
        if (!is_array($login_history)) {
            $login_history = [];
        }

        // Sortuj od najnowszych
        usort($login_history, function($a, $b) {
            return strtotime($b['date'] ?? '') - strtotime($a['date'] ?? '');
        });

        // Paginacja
        $total = count($login_history);
        $total_pages = max(1, ceil($total / $per_page));
        $page = min($page, $total_pages);
        $offset = ($page - 1) * $per_page;

        $paginated = array_slice($login_history, $offset, $per_page);

        wp_send_json_success([
            'items' => $paginated,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'pages' => $total_pages
            ]
        ]);
    }
}
