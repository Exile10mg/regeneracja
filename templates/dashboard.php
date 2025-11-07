<?php
/**
 * Template: Dashboard (Panel użytkownika)
 */

if (!defined('ABSPATH')) exit;

use UserPortal\Core\Auth;

$user = wp_get_current_user();

// Avatar (user_meta → fallback Gravatar) - używamy kp_avatar_id dla kompatybilności
$avatar_id  = (int) get_user_meta($user->ID, 'kp_avatar_id', true);
$avatar_url = $avatar_id ? wp_get_attachment_image_url($avatar_id, 'thumbnail') : get_avatar_url($user->ID);

// Imię i nazwisko
$first = trim( (string) get_user_meta($user->ID, 'first_name', true) );
$last  = trim( (string) get_user_meta($user->ID, 'last_name',  true) );
$fullname = trim($first . ' ' . $last);
$display  = $fullname !== '' ? $fullname : ( $user->display_name ?: $user->user_login );

// Rola -> własne etykiety
$role = 'Użytkownik';
if ( is_array( $user->roles ) && ! empty( $user->roles ) ) {
    if ( in_array( 'subscriber', $user->roles, true ) ) {
        $role = 'Pracownik';
    } else {
        $first_role = $user->roles[0];
        $role  = translate_user_role( wp_roles()->roles[ $first_role ]['name'] ?? $first_role );
    }
}

?>

<!-- Upewnij się że UPPANEL jest dostępny zanim załadują się inne skrypty -->
<script>
window.UPPANEL = window.UPPANEL || {};
window.UPPANEL.ajax_url = <?php echo json_encode(admin_url('admin-ajax.php')); ?>;
window.UPPANEL.nonce = <?php echo json_encode(wp_create_nonce('up_load_view')); ?>;
window.UPPANEL.report_nonce = <?php echo json_encode(wp_create_nonce('up_report_action')); ?>;
window.UPPANEL.avatar_nonce = <?php echo json_encode(wp_create_nonce('up_avatar_action')); ?>;
window.UPPANEL.profile_nonce = <?php echo json_encode(wp_create_nonce('up_avatar_action')); ?>;
window.UPPANEL.calendar_js_url = <?php echo json_encode(UP_URL . 'assets/js/calendar.js'); ?>;
window.UPPANEL.my_reports_js_url = <?php echo json_encode(UP_URL . 'assets/js/my-reports.js'); ?>;
window.UPPANEL.stats_js_url = <?php echo json_encode(UP_URL . 'assets/js/stats.js'); ?>;
window.UPPANEL.profile_js_url = <?php echo json_encode(UP_URL . 'assets/js/profile.js'); ?>;
console.log('✓ UPPANEL zainicjalizowany inline w dashboard.php:', window.UPPANEL);
</script>

<div class="kp-panel" data-logout-url="<?php echo esc_attr( Auth::get_logout_url() ); ?>">

  <!-- TOPBAR: user left + brand right -->
  <header class="kp-topbar">

    <!-- KARTA UŻYTKOWNIKA (LEWA) -->
    <div class="kp-usercard">
      <div class="kp-usercard-left">
        <div class="kp-avatar-wrap">
          <img
            src="<?php echo esc_url($avatar_url); ?>"
            alt="Avatar użytkownika"
            class="kp-usercard-avatar"
            id="up-avatar-img"
          >
          <button id="up-avatar-change" type="button" aria-label="Zmień avatar">
            <i class="fas fa-pen"></i>
          </button>
        </div>
        <input type="file" id="up-avatar-input" accept="image/*" hidden>
      </div>
      <div class="kp-usercard-right">
        <div class="kp-usercard-welcome">
          <h3>Witaj, <?php echo esc_html($display); ?>!</h3>
          <p>Twoje uprawnienia: <span class="kp-badge"><?php echo esc_html($role); ?></span></p>
          <div id="up-avatar-msg" aria-live="polite"></div>
        </div>
      </div>
    </div>

    <!-- BRAND + opis (PRAWA) -->
    <div class="kp-brand-info">
      <div class="kp-brand">
        <div class="kp-logo">SRP</div>
        <div class="kp-appname">System Rejestracji Pracy</div>
      </div>
      <p class="kp-brand-desc">Tutaj wprowadzisz dzisiejszy raport i sprawdzisz swoje zestawienia.</p>
    </div>
  </header>

  <div class="kp-shell">
    <!-- MENU -->
    <aside class="kp-sidebar" role="navigation" aria-label="Menu panelu">
      <ul>
        <li data-view="today" class="active" tabindex="0"><i class="far fa-calendar-check"></i> Dzisiejszy raport</li>
        <li data-view="my-reports" tabindex="0"><i class="far fa-file-alt"></i> Moje raporty</li>
        <li data-view="calendar" tabindex="0"><i class="far fa-calendar"></i> Kalendarz</li>
        <li data-view="stats" tabindex="0"><i class="fas fa-chart-bar"></i> Statystyki</li>
        <li data-view="profile" tabindex="0"><i class="fas fa-user-cog"></i> Profil</li>
        <?php if (current_user_can('manage_options')): ?>
        <li data-view="team" tabindex="0" class="has-submenu">
          <div class="menu-item-content">
            <span><i class="fas fa-users"></i> Raporty zespołu</span>
            <i class="fas fa-chevron-down submenu-arrow"></i>
          </div>
          <ul class="submenu">
            <li data-view="team" data-submenu="overview" tabindex="0"><i class="fas fa-chart-line"></i> Przegląd ogólny</li>
            <li data-view="team" data-submenu="pompy_cr" tabindex="0"><i class="fas fa-cog"></i> Pompy CR</li>
            <li data-view="team" data-submenu="pompy_vp" tabindex="0"><i class="fas fa-pump-soap"></i> Pompy VP</li>
            <li data-view="team" data-submenu="wtryski_cri" tabindex="0"><i class="fas fa-syringe"></i> Wtryski/CRi</li>
            <li data-view="team" data-submenu="turbo" tabindex="0"><i class="fas fa-fan"></i> Turbo</li>
          </ul>
        </li>
        <?php endif; ?>
        <li data-view="logout" tabindex="0"><i class="fas fa-sign-out-alt"></i> Wyloguj</li>
      </ul>
    </aside>

    <!-- ZAWARTOŚĆ -->
    <main class="kp-content" role="main">
      <!-- Domyślny widok: today -->
      <div id="kp-view-container" data-default-view="today"></div>
    </main>
  </div>
</div>
