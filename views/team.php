<?php
/**
 * Widok: Raporty zespołu (tylko dla administratorów)
 * Pokazuje kto złożył raporty, kto nie, oraz podsumowanie pracy zespołu
 */

if (!defined('ABSPATH')) exit;

// Sprawdź uprawnienia
if (!current_user_can('manage_options')) {
    wp_die(__('Nie masz uprawnień do przeglądania tej strony.', 'user-portal'));
}

// Pobierz parametr submenu (overview, pompy_cr, pompy_vp, wtryski_cri, turbo)
$submenu = isset($_GET['submenu']) ? sanitize_text_field($_GET['submenu']) : 'overview';

// Pobierz datę z parametru specyficznego dla submenu (np. pompy_cr_date, overview_date)
$dateParam = $submenu . '_date';
$requestedDate = isset($_GET[$dateParam]) ? sanitize_text_field($_GET[$dateParam]) : '';
if ($requestedDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)) {
    $today = $requestedDate;
} else {
    $today = date('Y-m-d');
}
$today_label = date_i18n('l, j F Y', strtotime($today));

// Pobierz wszystkich użytkowników (z rolą subscriber lub wyższą)
$users = get_users([
    'role__in' => ['subscriber', 'contributor', 'author', 'editor', 'administrator'],
    'orderby' => 'display_name',
    'order' => 'ASC'
]);

// Przeanalizuj raporty
$stats = [
    'total_users' => 0,
    'submitted' => 0,
    'drafts' => 0,
    'missing' => 0,
    'users_with_reports' => [],
    'users_without_reports' => []
];

foreach ($users as $user) {
    $stats['total_users']++;

    // Pobierz raporty użytkownika
    $all_reports = get_user_meta($user->ID, 'kp_reports', true);
    if (!is_array($all_reports)) $all_reports = [];

    $today_report = isset($all_reports[$today]) ? $all_reports[$today] : null;
    $status = $today_report ? ($today_report['status'] ?? '') : '';

    // Imię i nazwisko
    $first_name = get_user_meta($user->ID, 'first_name', true);
    $last_name = get_user_meta($user->ID, 'last_name', true);
    $display_name = trim($first_name . ' ' . $last_name);
    if (empty($display_name)) {
        $display_name = $user->display_name ?: $user->user_login;
    }

    $user_data = [
        'ID' => $user->ID,
        'name' => $display_name,
        'email' => $user->user_email,
        'status' => $status,
        'report' => $today_report,
        'general_notes' => $today_report['note'] ?? ''
    ];

    if ($status === 'submitted') {
        $stats['submitted']++;
        $stats['users_with_reports'][] = $user_data;
    } elseif ($status === 'draft') {
        $stats['drafts']++;
        $stats['users_with_reports'][] = $user_data;
    } else {
        $stats['missing']++;
        $stats['users_without_reports'][] = $user_data;
    }
}

// Oblicz procent złożonych raportów
$submitted_percentage = $stats['total_users'] > 0
    ? round(($stats['submitted'] / $stats['total_users']) * 100)
    : 0;

// Definicja kategorii (zgodna z today.php)
$up_CATEGORIES = [
  'pompy_cr' => [
    'label' => 'Pompy CR',
    'icon' => 'fa-cog',
    'color' => '#ED1C24',
    'tasks' => [
      'piaskowanie' => ['label' => 'Piaskowanie CR', 'icon' => 'fa-spray-can'],
      'rozbieranie' => ['label' => 'Rozbieranie CR', 'icon' => 'fa-screwdriver-wrench'],
      'czyszczenie' => ['label' => 'Czyszczenie CR', 'icon' => 'fa-soap'],
      'skladanie'   => ['label' => 'Składanie CR', 'icon' => 'fa-puzzle-piece'],
      'walki'       => ['label' => 'Wałki CR', 'icon' => 'fa-circle-notch'],
      'inne'        => ['label' => 'Inne CR', 'icon' => 'fa-ellipsis-h'],
    ]
  ],
  'pompy_vp' => [
    'label' => 'Pompy VP',
    'icon' => 'fa-pump-soap',
    'color' => '#2196F3',
    'tasks' => [
      'piaskowanie_vp' => ['label' => 'Piaskowanie VP', 'icon' => 'fa-spray-can'],
      'skladanie_vp'   => ['label' => 'Składanie VP', 'icon' => 'fa-puzzle-piece'],
      'kalibracja_vp'  => ['label' => 'Kalibracja VP', 'icon' => 'fa-ruler-combined'],
      'sterowniki_przygotowanie_vp' => ['label' => 'Sterowniki przygotowanie VP', 'icon' => 'fa-microchip'],
      'sterowniki_naprawa_vp' => ['label' => 'Sterowniki naprawa VP', 'icon' => 'fa-tools'],
      'inne_vp' => ['label' => 'Inne VP', 'icon' => 'fa-ellipsis-h'],
    ]
  ],
  'wtryski_cri' => [
    'label' => 'Wtryski/CRi',
    'icon' => 'fa-syringe',
    'color' => '#FF9800',
    'tasks' => [
      'testowanie_cri' => ['label' => 'Testowanie CRi', 'icon' => 'fa-vial'],
      'regeneracja_cri' => ['label' => 'Regeneracja CRi', 'icon' => 'fa-recycle'],
      'inne_cri' => ['label' => 'Inne CRi', 'icon' => 'fa-ellipsis-h'],
    ]
  ],
  'turbo' => [
    'label' => 'Turbo',
    'icon' => 'fa-fan',
    'color' => '#4CAF50',
    'tasks' => [
      'piaskowanie_turbo' => ['label' => 'Piaskowanie turbo', 'icon' => 'fa-spray-can'],
      'skladanie_turbo' => ['label' => 'Składanie turbo', 'icon' => 'fa-puzzle-piece'],
      'inne_turbo' => ['label' => 'Inne turbo', 'icon' => 'fa-ellipsis-h'],
    ]
  ],
];

// Zbierz dane dla każdej kategorii
$category_data = [];
foreach ($up_CATEGORIES as $catKey => $catInfo) {
    $category_data[$catKey] = [
        'total_qty' => 0,
        'total_time' => 0,
        'users_data' => [],
        'tasks_stats' => [] // Statystyki per zadanie
    ];

    // Inicjalizuj statystyki zadań
    foreach ($catInfo['tasks'] as $taskKey => $taskLabel) {
        $category_data[$catKey]['tasks_stats'][$taskKey] = [
            'label' => $taskLabel,
            'total_qty' => 0
        ];
    }

    foreach ($stats['users_with_reports'] as $user_data) {
        if (!$user_data['report'] || !isset($user_data['report']['tasks'][$catKey])) continue;

        $cat_tasks = $user_data['report']['tasks'][$catKey];
        $user_total_qty = 0;
        $user_tasks_detail = [];

        foreach ($catInfo['tasks'] as $taskKey => $taskLabel) {
            if (!isset($cat_tasks[$taskKey])) continue;

            $qty = (int)($cat_tasks[$taskKey]['qty'] ?? 0);
            $time = (string)($cat_tasks[$taskKey]['time'] ?? '');
            $note = (string)($cat_tasks[$taskKey]['note'] ?? '');

            if ($qty > 0) {
                $user_total_qty += $qty;
                $category_data[$catKey]['total_qty'] += $qty;

                // Dodaj do statystyk zadania
                $category_data[$catKey]['tasks_stats'][$taskKey]['total_qty'] += $qty;

                $user_tasks_detail[] = [
                    'label' => $taskLabel,
                    'qty' => $qty,
                    'time' => $time,
                    'note' => $note
                ];
            }
        }

        if ($user_total_qty > 0) {
            $category_data[$catKey]['users_data'][] = [
                'name' => $user_data['name'],
                'total_qty' => $user_total_qty,
                'tasks' => $user_tasks_detail,
                'general_notes' => $user_data['general_notes']
            ];
        }
    }

    // Sortuj użytkowników po ilości (malejąco)
    usort($category_data[$catKey]['users_data'], function($a, $b) {
        return $b['total_qty'] - $a['total_qty'];
    });

    // Sortuj zadania po ilości (malejąco)
    uasort($category_data[$catKey]['tasks_stats'], function($a, $b) {
        return $b['total_qty'] - $a['total_qty'];
    });
}
?>

<style>
/* ====== Raporty zespołu - nowoczesny styl ====== */
.team-reports {
    padding: 0;
    color: #333;
}

.team-header {
    margin-bottom: 24px;
}

.team-title {
    margin: 0 0 8px 0;
    font-size: 28px;
    font-weight: 700;
    color: #222;
    display: flex;
    align-items: center;
    gap: 12px;
}

.team-title::before {
    content: "\f0c0";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    color: #ED1C24;
    font-size: 28px;
}

.team-subtitle {
    color: #666;
    font-size: 14px;
    margin: 0;
}

/* KPI Cards */
.team-kpi {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}

.kpi-card {
    background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.kpi-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #ED1C24 0%, #ff4d4d 100%);
}

.kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
    border-color: #ED1C24;
}

.kpi-value {
    font-size: 36px;
    font-weight: 700;
    color: #ED1C24;
    line-height: 1;
    margin: 4px 0;
}

.kpi-label {
    font-size: 13px;
    color: #666;
    font-weight: 600;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.kpi-card--success::before {
    background: linear-gradient(90deg, #4CAF50 0%, #66BB6A 100%);
}

.kpi-card--success .kpi-value {
    color: #4CAF50;
}

.kpi-card--warning::before {
    background: linear-gradient(90deg, #FF9800 0%, #FFB74D 100%);
}

.kpi-card--warning .kpi-value {
    color: #FF9800;
}

.kpi-card--danger::before {
    background: linear-gradient(90deg, #f44336 0%, #ef5350 100%);
}

.kpi-card--danger .kpi-value {
    color: #f44336;
}

/* Progress bar */
.kpi-progress {
    width: 100%;
    height: 8px;
    background: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 8px;
}

.kpi-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #4CAF50 0%, #66BB6A 100%);
    border-radius: 4px;
    transition: width 0.5s ease;
}

/* Section Headers */
.team-section {
    margin-bottom: 32px;
}

.team-section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #e0e0e0;
}

.team-section-title {
    font-size: 20px;
    font-weight: 700;
    color: #222;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.team-section-title i {
    color: #ED1C24;
}

.team-section-count {
    background: #ED1C24;
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
}

/* User Cards */
.team-users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}

.user-card {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.3s ease;
}

.user-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.user-card--submitted {
    border-left: 4px solid #4CAF50;
}

.user-card--submitted:hover {
    border-color: #4CAF50;
}

.user-card--draft {
    border-left: 4px solid #FF9800;
}

.user-card--draft:hover {
    border-color: #FF9800;
}

.user-card--missing {
    border-left: 4px solid #f44336;
}

.user-card--missing:hover {
    border-color: #f44336;
}

.user-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ED1C24 0%, #ff4d4d 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 18px;
    flex-shrink: 0;
    text-transform: uppercase;
}

.user-info {
    flex: 1;
    min-width: 0;
}

.user-name {
    font-weight: 600;
    color: #222;
    margin: 0 0 4px 0;
    font-size: 15px;
}

.user-email {
    font-size: 12px;
    color: #999;
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    flex-shrink: 0;
}

.user-status i {
    font-size: 14px;
}

.user-status--submitted {
    background: rgba(76, 175, 80, 0.1);
    color: #4CAF50;
}

.user-status--draft {
    background: rgba(255, 152, 0, 0.1);
    color: #FF9800;
}

.user-status--missing {
    background: rgba(244, 67, 54, 0.1);
    color: #f44336;
}

/* Empty state */
.team-empty {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.team-empty i {
    font-size: 64px;
    color: #ddd;
    margin-bottom: 16px;
}

.team-empty p {
    font-size: 16px;
    margin: 0;
}

/* Category Header with Date Selector */
.category-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin: 32px 0 24px 0;
    padding: 24px;
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border: 2px solid #e0e0e0;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}

.category-header__title {
    display: flex;
    align-items: center;
    gap: 16px;
}

.category-header__icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.category-header__name {
    margin: 0 0 4px 0;
    font-size: 26px;
    font-weight: 700;
    color: #222;
}

.category-header__desc {
    margin: 0;
    font-size: 14px;
    color: #666;
}

.category-header__controls {
    display: flex;
    gap: 16px;
    align-items: center;
}

.date-selector {
    display: flex;
    align-items: center;
    gap: 12px;
    background: white;
    padding: 12px 18px;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
    transition: all 0.3s ease;
}

.date-selector:hover {
    border-color: #ED1C24;
    box-shadow: 0 4px 12px rgba(237,28,36,0.15);
}

.date-selector label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 14px;
    color: #333;
    cursor: pointer;
    margin: 0;
}

.date-selector label i {
    color: #ED1C24;
    font-size: 16px;
}

.date-selector input[type="date"] {
    padding: 8px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #333;
    background: #f8f9fa;
    transition: all 0.2s ease;
    cursor: pointer;
}

.date-selector input[type="date"]:hover {
    border-color: #ED1C24;
    background: white;
}

.date-selector input[type="date"]:focus {
    outline: none;
    border-color: #ED1C24;
    background: white;
    box-shadow: 0 0 0 3px rgba(237,28,36,0.1);
}

.date-selector .date-label {
    font-size: 13px;
    color: #666;
    font-weight: 500;
    padding: 6px 12px;
    background: #f8f9fa;
    border-radius: 6px;
}

@media (max-width: 768px) {
    .category-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .category-header__controls {
        width: 100%;
    }

    .date-selector {
        width: 100%;
        flex-wrap: wrap;
    }
}

/* Category Tabs */
.category-tabs {
    display: flex;
    gap: 12px;
    margin: 32px 0 24px 0;
    border-bottom: 3px solid #e0e0e0;
    flex-wrap: wrap;
    padding-bottom: 0;
}

.category-tab {
    padding: 14px 24px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: #666;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: -3px;
    border-radius: 8px 8px 0 0;
}

.category-tab i {
    font-size: 18px;
}

.category-tab:hover {
    background: #f5f5f5;
    color: #333;
}

.category-tab.active {
    color: #fff;
    border-bottom-color: transparent;
    background: linear-gradient(135deg, #ED1C24 0%, #ff4d4d 100%);
}

.category-tab.active:hover {
    background: linear-gradient(135deg, #ff2830 0%, #ED1C24 100%);
}

/* Category Content */
.category-content {
    display: none;
}

.category-content.active {
    display: block;
    animation: fadeIn 0.4s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Category Summary Stats */
.category-summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.category-stat-card {
    background: white;
    border: 2px solid #e0e0e0;
    border-left: 4px solid #ED1C24;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.3s ease;
}

.category-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    border-color: currentColor;
}

.category-stat-card__icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.category-stat-card__content {
    flex: 1;
}

.category-stat-card__value {
    font-size: 36px;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 6px;
}

.category-stat-card__label {
    font-size: 13px;
    color: #666;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

@media (max-width: 640px) {
    .category-summary-stats {
        grid-template-columns: 1fr;
    }
}

/* User Work Cards */
.user-work-cards {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.user-work-card {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
}

.user-work-card:hover {
    border-color: #ED1C24;
    box-shadow: 0 4px 12px rgba(237, 28, 36, 0.15);
    transform: translateY(-2px);
}

.user-work-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f5f5f5;
}

.user-work-card__user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-work-card__avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ED1C24 0%, #ff4d4d 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 16px;
    text-transform: uppercase;
}

.user-work-card__name {
    font-size: 16px;
    font-weight: 700;
    color: #222;
    margin: 0;
}

.user-work-card__total {
    background: linear-gradient(135deg, #ED1C24 0%, #ff4d4d 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.user-work-card__tasks {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}

.task-item {
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.task-item__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.task-item__name {
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.task-item__qty {
    background: #ED1C24;
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 13px;
}

.task-item__meta {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: #666;
}

.task-item__time {
    display: flex;
    align-items: center;
    gap: 4px;
}

.task-item__note {
    margin-top: 4px;
    padding: 8px 10px;
    background: white;
    border-left: 3px solid #ED1C24;
    border-radius: 4px;
    font-size: 12px;
    color: #555;
    font-style: italic;
    line-height: 1.4;
}

.category-empty {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.category-empty i {
    font-size: 64px;
    color: #ddd;
    margin-bottom: 16px;
}

.category-empty p {
    font-size: 16px;
    margin: 8px 0 0 0;
}

/* Responsive */
@media (max-width: 768px) {
    .team-kpi {
        grid-template-columns: repeat(2, 1fr);
    }

    .team-users-grid {
        grid-template-columns: 1fr;
    }

    .team-title {
        font-size: 24px;
    }

    .category-tabs {
        gap: 8px;
    }

    .category-tab {
        padding: 12px 16px;
        font-size: 14px;
    }

    .category-summary {
        flex-direction: column;
        align-items: flex-start;
    }

    .category-summary__stats {
        width: 100%;
        justify-content: space-around;
    }

    .user-work-card__tasks {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .team-kpi {
        grid-template-columns: 1fr;
    }

    .kpi-value {
        font-size: 32px;
    }

    .category-tab {
        padding: 10px 14px;
        font-size: 13px;
    }

    .category-tab i {
        font-size: 16px;
    }
}

/* General Notes Box */
.general-notes-box {
    margin-top: 16px;
    padding: 16px 18px;
    background: linear-gradient(135deg, #FFF9E6 0%, #FFFBF0 100%);
    border-left: 4px solid #FFC107;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(255, 193, 7, 0.1);
    text-align: left;
}

.general-notes-box__header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    font-size: 13px;
    color: #F57C00;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.general-notes-box__header i {
    font-size: 15px;
}

.general-notes-box__content {
    font-size: 15px;
    color: #333;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
    text-align: left;
}

/* Modal for Full Report */
.team-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.2s ease;
}

.team-modal {
    background: white;
    border-radius: 16px;
    max-width: 900px;
    width: 100%;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    display: flex;
    flex-direction: column;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.team-modal__header {
    padding: 24px 28px;
    border-bottom: 2px solid #e0e0e0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
}

.team-modal__title {
    display: flex;
    align-items: center;
    gap: 16px;
}

.team-modal__avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ED1C24 0%, #ff4d4d 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 20px;
    text-transform: uppercase;
}

.team-modal__user-info h3 {
    margin: 0 0 4px 0;
    font-size: 22px;
    font-weight: 700;
    color: #222;
}

.team-modal__user-info p {
    margin: 0;
    font-size: 14px;
    color: #666;
}

.team-modal__close {
    background: transparent;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    color: #666;
    font-size: 20px;
}

.team-modal__close:hover {
    background: #f5f5f5;
    color: #ED1C24;
}

.team-modal__body {
    padding: 28px;
    overflow-y: auto;
    flex: 1;
}

.team-modal__category-section {
    margin-bottom: 32px;
}

.team-modal__category-section:last-child {
    margin-bottom: 0;
}

.team-modal__category-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f0f0f0;
}

.team-modal__category-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
}

.team-modal__category-name {
    font-size: 18px;
    font-weight: 700;
    color: #222;
    margin: 0;
}

.team-modal__tasks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}

.team-modal__general-notes {
    margin-top: 32px;
    padding: 20px;
    background: linear-gradient(135deg, #FFF9E6 0%, #FFFBF0 100%);
    border-left: 4px solid #FFC107;
    border-radius: 12px;
}

.team-modal__general-notes h4 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 12px 0;
    font-size: 16px;
    font-weight: 700;
    color: #F57C00;
}

.team-modal__general-notes p {
    margin: 0;
    font-size: 15px;
    color: #555;
    line-height: 1.6;
    white-space: pre-wrap;
    text-align: left;
}

.team-modal__empty {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

.team-modal__empty i {
    font-size: 48px;
    color: #ddd;
    margin-bottom: 12px;
}

@media (max-width: 768px) {
    .team-modal {
        max-width: 100%;
        max-height: 100vh;
        border-radius: 0;
    }

    .team-modal__tasks-grid {
        grid-template-columns: 1fr;
    }
}

/* User card clickable */
.user-card {
    cursor: pointer;
    transition: all 0.3s ease;
}

.user-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(237, 28, 36, 0.2);
}

.user-work-card {
    position: relative;
}

.user-work-card__general-notes {
    margin-top: 16px;
}

.user-work-card__general-notes .general-notes-box__content {
    text-align: left !important;
}
</style>

<div class="team-reports">
    <!-- Header -->
    <div class="team-header">
        <h2 class="team-title">Raporty zespołu</h2>
        <p class="team-subtitle">
            <strong><?php echo esc_html($today_label); ?></strong>
            &nbsp;•&nbsp;
            Stan na <?php echo date_i18n('H:i'); ?>
        </p>
    </div>

    <?php if ($submenu === 'overview'): ?>
    <!-- Date Selector for Overview -->
    <div class="category-header">
        <div class="category-header__title">
            <div class="category-header__icon" style="background: linear-gradient(135deg, #ED1C24 0%, #ff4d4d 100%);">
                <i class="fas fa-chart-line"></i>
            </div>
            <div>
                <h2 class="category-header__name">Przegląd ogólny</h2>
                <p class="category-header__desc">Wszystkie raporty pracowników</p>
            </div>
        </div>
        <div class="category-header__controls">
            <div class="date-selector">
                <label for="team-date-picker-overview">
                    <i class="far fa-calendar"></i>
                    <span>Wybierz dzień:</span>
                </label>
                <input
                    type="date"
                    id="team-date-picker-overview"
                    value="<?php echo esc_attr($today); ?>"
                    max="<?php echo date('Y-m-d'); ?>"
                    onchange="changeTeamDate(this.value, 'overview')">
                <span class="date-label"><?php echo esc_html($today_label); ?></span>
            </div>
        </div>
    </div>

    <!-- KPI Cards - tylko dla overview -->
    <div class="team-kpi">
        <div class="kpi-card">
            <div class="kpi-label">Wszyscy pracownicy</div>
            <div class="kpi-value"><?php echo $stats['total_users']; ?></div>
        </div>

        <div class="kpi-card kpi-card--success">
            <div class="kpi-label">Złożone raporty</div>
            <div class="kpi-value"><?php echo $stats['submitted']; ?></div>
            <div class="kpi-progress">
                <div class="kpi-progress-bar" style="width: <?php echo $submitted_percentage; ?>%;"></div>
            </div>
        </div>

        <div class="kpi-card kpi-card--danger">
            <div class="kpi-label">Brak raportu</div>
            <div class="kpi-value"><?php echo $stats['missing']; ?></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($submenu !== 'overview'):
        // Widok konkretnej kategorii
        if (isset($up_CATEGORIES[$submenu])):
            $catKey = $submenu;
            $catInfo = $up_CATEGORIES[$catKey];
            $catData = $category_data[$catKey];
            $totalQty = $catData['total_qty'];
            $usersCount = count($catData['users_data']);
    ?>
        <!-- Nagłówek kategorii z selektorem daty -->
        <div class="category-header">
            <div class="category-header__title">
                <div class="category-header__icon" style="background: linear-gradient(135deg, <?php echo esc_attr($catInfo['color']); ?> 0%, <?php echo esc_attr($catInfo['color']); ?>dd 100%);">
                    <i class="fas <?php echo esc_attr($catInfo['icon']); ?>"></i>
                </div>
                <div>
                    <h2 class="category-header__name"><?php echo esc_html($catInfo['label']); ?></h2>
                    <p class="category-header__desc">Szczegółowe raporty pracy zespołu</p>
                </div>
            </div>
            <div class="category-header__controls">
                <div class="date-selector">
                    <label for="team-date-picker">
                        <i class="far fa-calendar"></i>
                        <span>Wybierz dzień:</span>
                    </label>
                    <input
                        type="date"
                        id="team-date-picker"
                        value="<?php echo esc_attr($today); ?>"
                        max="<?php echo date('Y-m-d'); ?>"
                        onchange="changeTeamDate(this.value, '<?php echo esc_js($submenu); ?>')">
                    <span class="date-label"><?php echo esc_html($today_label); ?></span>
                </div>
            </div>
        </div>

        <div class="category-content active" id="category-<?php echo esc_attr($catKey); ?>">
            <!-- Podsumowanie statystyk zadań -->
            <div class="category-summary-stats">
                <?php
                // Wyświetl tylko zadania z ilością > 0
                $topTasks = array_filter($catData['tasks_stats'], function($task) {
                    return $task['total_qty'] > 0;
                });

                foreach ($topTasks as $taskKey => $taskData):
                ?>
                    <div class="category-stat-card" style="border-left-color: <?php echo esc_attr($catInfo['color']); ?>;">
                        <div class="category-stat-card__icon" style="background: <?php echo esc_attr($catInfo['color']); ?>20;">
                            <i class="fas fa-box" style="color: <?php echo esc_attr($catInfo['color']); ?>;"></i>
                        </div>
                        <div class="category-stat-card__content">
                            <div class="category-stat-card__value" style="color: <?php echo esc_attr($catInfo['color']); ?>;"><?php echo $taskData['total_qty']; ?></div>
                            <div class="category-stat-card__label"><?php echo esc_html($taskData['label']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Karty pracowników -->
            <?php if (!empty($catData['users_data'])): ?>
                <div class="user-work-cards">
                    <?php foreach ($catData['users_data'] as $userData):
                        // Inicjały
                        $initials = '';
                        $name_parts = explode(' ', $userData['name']);
                        foreach ($name_parts as $part) {
                            if (!empty($part)) {
                                $initials .= mb_substr($part, 0, 1);
                            }
                        }
                        $initials = mb_substr($initials, 0, 2);
                    ?>
                        <div class="user-work-card">
                            <div class="user-work-card__header">
                                <div class="user-work-card__user">
                                    <div class="user-work-card__avatar" style="background: linear-gradient(135deg, <?php echo esc_attr($catInfo['color']); ?> 0%, <?php echo esc_attr($catInfo['color']); ?>dd 100%);">
                                        <?php echo esc_html($initials); ?>
                                    </div>
                                    <h4 class="user-work-card__name"><?php echo esc_html($userData['name']); ?></h4>
                                </div>
                                <div class="user-work-card__total" style="background: linear-gradient(135deg, <?php echo esc_attr($catInfo['color']); ?> 0%, <?php echo esc_attr($catInfo['color']); ?>dd 100%);">
                                    <i class="fas fa-check-circle"></i>
                                    <?php echo $userData['total_qty']; ?> szt.
                                </div>
                            </div>

                            <div class="user-work-card__tasks">
                                <?php foreach ($userData['tasks'] as $task): ?>
                                    <div class="task-item">
                                        <div class="task-item__header">
                                            <span class="task-item__name"><?php echo esc_html($task['label']); ?></span>
                                            <span class="task-item__qty" style="background: <?php echo esc_attr($catInfo['color']); ?>;">
                                                <?php echo $task['qty']; ?> szt.
                                            </span>
                                        </div>
                                        <?php if (!empty($task['time']) || !empty($task['note'])): ?>
                                            <div class="task-item__meta">
                                                <?php if (!empty($task['time'])): ?>
                                                    <div class="task-item__time">
                                                        <i class="far fa-clock"></i>
                                                        <?php echo esc_html($task['time']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($task['note'])): ?>
                                            <div class="task-item__note" style="border-left-color: <?php echo esc_attr($catInfo['color']); ?>;">
                                                <i class="far fa-comment-dots"></i> <?php echo esc_html($task['note']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Uwagi ogólne -->
                            <?php if (!empty($userData['general_notes'])): ?>
                                <div class="user-work-card__general-notes">
                                    <div class="general-notes-box">
                                        <div class="general-notes-box__header">
                                            <i class="fas fa-clipboard-list"></i>
                                            Uwagi ogólne
                                        </div>
                                        <div class="general-notes-box__content" style="text-align: left;">
                                            <?php echo esc_html($userData['general_notes']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="category-empty">
                    <i class="fas fa-inbox"></i>
                    <p>Brak danych dla tej kategorii</p>
                </div>
            <?php endif; ?>
        </div>
    <?php
        endif; // isset($up_CATEGORIES[$submenu])
    else:
        // Widok overview - lista pracowników
    ?>

    <!-- Sekcja: Pracownicy ze złożonymi raportami -->
    <?php if (!empty($stats['users_with_reports'])): ?>
        <div class="team-section" style="margin-top: 48px;">
            <div class="team-section-header">
                <h3 class="team-section-title">
                    <i class="fas fa-check-circle"></i>
                    Pracownicy z raportami
                </h3>
                <span class="team-section-count"><?php echo count($stats['users_with_reports']); ?></span>
            </div>

            <div class="team-users-grid">
                <?php foreach ($stats['users_with_reports'] as $user_data):
                    $initials = '';
                    $name_parts = explode(' ', $user_data['name']);
                    foreach ($name_parts as $part) {
                        if (!empty($part)) {
                            $initials .= mb_substr($part, 0, 1);
                        }
                    }
                    $initials = mb_substr($initials, 0, 2);

                    $status_class = $user_data['status'] === 'submitted' ? 'submitted' : 'draft';
                    $status_label = $user_data['status'] === 'submitted' ? 'Złożony' : 'Szkic';
                    $status_icon = $user_data['status'] === 'submitted' ? 'fa-check-circle' : 'fa-pencil';
                    // Serializuj dane raportu do JSON
                    $report_json = htmlspecialchars(json_encode($user_data), ENT_QUOTES, 'UTF-8');
                ?>
                    <div class="user-card user-card--<?php echo $status_class; ?>"
                         data-user-report='<?php echo $report_json; ?>'
                         onclick="openTeamReportModal(this)">
                        <div class="user-avatar">
                            <?php echo esc_html($initials); ?>
                        </div>
                        <div class="user-info">
                            <h4 class="user-name"><?php echo esc_html($user_data['name']); ?></h4>
                            <p class="user-email"><?php echo esc_html($user_data['email']); ?></p>
                        </div>
                        <div class="user-status user-status--<?php echo $status_class; ?>">
                            <i class="fas <?php echo $status_icon; ?>"></i>
                            <?php echo $status_label; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Sekcja: Pracownicy bez raportów -->
    <?php if (!empty($stats['users_without_reports'])): ?>
        <div class="team-section">
            <div class="team-section-header">
                <h3 class="team-section-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Pracownicy bez raportów
                </h3>
                <span class="team-section-count"><?php echo count($stats['users_without_reports']); ?></span>
            </div>

            <div class="team-users-grid">
                <?php foreach ($stats['users_without_reports'] as $user_data):
                    $initials = '';
                    $name_parts = explode(' ', $user_data['name']);
                    foreach ($name_parts as $part) {
                        if (!empty($part)) {
                            $initials .= mb_substr($part, 0, 1);
                        }
                    }
                    $initials = mb_substr($initials, 0, 2);
                ?>
                    <div class="user-card user-card--missing">
                        <div class="user-avatar" style="background: linear-gradient(135deg, #999 0%, #777 100%);">
                            <?php echo esc_html($initials); ?>
                        </div>
                        <div class="user-info">
                            <h4 class="user-name"><?php echo esc_html($user_data['name']); ?></h4>
                            <p class="user-email"><?php echo esc_html($user_data['email']); ?></p>
                        </div>
                        <div class="user-status user-status--missing">
                            <i class="fas fa-times-circle"></i>
                            Brak
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php endif; // if ($submenu !== 'overview') ?>

    <?php if ($stats['total_users'] === 0): ?>
        <div class="team-empty">
            <i class="fas fa-users-slash"></i>
            <p>Brak użytkowników w systemie.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Dane kategorii dla JavaScript (ukryte) -->
<script type="application/json" id="up-categories-data">
<?php echo json_encode($up_CATEGORIES); ?>
</script>

<!-- Modal kontenera (będzie wypełniony przez JS) -->
<div id="team-report-modal-container"></div>
