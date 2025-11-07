<?php
/**
 * Szablon: Profil użytkownika (front)
 * Wymaga: zalogowany użytkownik
 * Zapewnia: HTML + AJAX handlery (upload avatara, zapis profilu, zmiana hasła, historia logowań)
 */
if ( ! defined('ABSPATH') ) exit;

if ( ! is_user_logged_in() ) {
    wp_die(__('Musisz być zalogowany, aby zobaczyć tę stronę.', 'kp'));
}

$current_user = wp_get_current_user();
$uid          = $current_user->ID;

// Avatar (user_meta → fallback Gravatar)
$avatar_id  = (int) get_user_meta($uid, 'kp_avatar_id', true);
$avatar_url = $avatar_id ? wp_get_attachment_image_url($avatar_id, 'medium') : get_avatar_url($uid, ['size' => 256]);

// Imię i nazwisko
$first = trim((string) get_user_meta($uid, 'first_name', true));
$last  = trim((string) get_user_meta($uid, 'last_name',  true));
$fullname = trim($first . ' ' . $last);
$display  = $fullname !== '' ? $fullname : ( $current_user->display_name ?: $current_user->user_login );

// Główna rola użytkownika → etykieta
$role_key = (is_array($current_user->roles) && $current_user->roles) ? $current_user->roles[0] : '';
$role_label = 'Użytkownik';
if ($role_key === 'administrator') $role_label = 'Administrator';
elseif ($role_key === 'subscriber') $role_label = 'Pracownik';

// Ostatnia aktywność
$last_activity = get_user_meta($uid, 'kp_last_activity', true);
$last_activity_formatted = $last_activity ? date('d.m.Y H:i', strtotime($last_activity)) : 'Nieznana';

// Nonce
$profile_nonce = wp_create_nonce('up_avatar_action');
?>

<div class="kp-profile-wrap">
  <div class="profile-container">

    <!-- GÓRNY NAGŁÓWEK -->
    <div class="profile-header">
      <div class="profile-avatar-block">
        <div id="profile-avatar-container" class="avatar" role="button" tabindex="0" aria-label="Zmień avatar">
          <img id="profile-avatar-img" src="<?php echo esc_url($avatar_url); ?>" alt="Avatar" />
          <div class="avatar-overlay">
            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="currentColor" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
            </svg>
            <span>Zmień</span>
          </div>
          <input id="avatar-upload" type="file" accept="image/*" hidden />
        </div>
        <div class="profile-name">
          <h1><?php echo esc_html($display); ?></h1>
          <div class="profile-role"><?php echo esc_html($role_label); ?></div>
        </div>
      </div>

      <div class="profile-actions">
        <button class="btn btn-primary btn-edit-profile" type="button">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" fill="currentColor"/>
          </svg>
          Edytuj profil
        </button>
        <button class="btn btn-outline security-btn" data-action="change-password" type="button">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M18,8A2,2 0 0,1 20,10V20A2,2 0 0,1 18,22H6A2,2 0 0,1 4,20V10A2,2 0 0,1 6,8H7V6A5,5 0 0,1 12,1A5,5 0 0,1 17,6V8H18M12,3A3,3 0 0,0 9,6V8H15V6A3,3 0 0,0 12,3Z" fill="currentColor"/>
          </svg>
          Zmień hasło
        </button>
      </div>
    </div>

    <!-- KAFELKI INFORMACYJNE -->
    <div class="profile-grid">
      <div class="profile-card">
        <div class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
            <path d="M22 6C22 4.9 21.1 4 20 4H4C2.9 4 2 4.9 2 6V18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6M20 6L12 11L4 6H20Z" fill="currentColor"/>
          </svg>
          Dane kontaktowe
        </div>
        <div class="card-content">
          <div class="card-row">
            <span>E-mail</span>
            <b><?php echo esc_html($current_user->user_email); ?></b>
          </div>
          <div class="card-row">
            <span>Login</span>
            <b><?php echo esc_html($current_user->user_login); ?></b>
          </div>
          <div class="card-row">
            <span>Imię</span>
            <b><?php echo esc_html($first ?: '—'); ?></b>
          </div>
          <div class="card-row">
            <span>Nazwisko</span>
            <b><?php echo esc_html($last ?: '—'); ?></b>
          </div>
        </div>
      </div>

      <div class="profile-card">
        <div class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
            <path d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,14C16.42,14 20,15.79 20,18V20H4V18C4,15.79 7.58,14 12,14Z" fill="currentColor"/>
          </svg>
          Uprawnienia
        </div>
        <div class="card-content">
          <div class="roles">
            <?php foreach ((array)$current_user->roles as $r): 
                $label = translate_user_role(ucfirst($r));
                if ($r === 'subscriber') {
                    $label = 'Pracownik';
                }
            ?>
              <span class="role-chip"><?php echo esc_html($label); ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="profile-card">
        <div class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
            <path d="M12,17A2,2 0 0,0 14,15C14,13.89 13.1,13 12,13A2,2 0 0,0 10,15A2,2 0 0,0 12,17M18,8A2,2 0 0,1 20,10V20A2,2 0 0,1 18,22H6A2,2 0 0,1 4,20V10C4,8.89 4.9,8 6,8H7V6A5,5 0 0,1 12,1A5,5 0 0,1 17,6V8H18M12,3A3,3 0 0,0 9,6V8H15V6A3,3 0 0,0 12,3Z" fill="currentColor"/>
          </svg>
          Bezpieczeństwo
        </div>
        <div class="card-content">
          <div class="card-row">
            <span>Ostatnia aktywność</span>
            <b><?php echo esc_html($last_activity_formatted); ?></b>
          </div>
          <p class="security-text">Utrzymuj bezpieczeństwo swojego konta poprzez regularne zmiany hasła.</p>
          <div class="security-actions">
            <button class="btn btn-outline security-btn" data-action="change-password" type="button">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M22,18V22H18V19H15V16H12L9.74,13.74C9.19,13.91 8.61,14 8,14A6,6 0 0,1 2,8A6,6 0 0,1 8,2A6,6 0 0,1 14,8C14,8.61 13.91,9.19 13.74,9.74L22,18M7,5A2,2 0 0,0 5,7A2,2 0 0,0 7,9A2,2 0 0,0 9,7A2,2 0 0,0 7,5Z" fill="currentColor"/>
              </svg>
              Zmień hasło
            </button>
            <button class="btn btn-outline security-btn" data-action="login-history" type="button">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M13.5,8H12V13L16.28,15.54L17,14.33L13.5,12.25V8M13,3A9,9 0 0,0 4,12H1L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3" fill="currentColor"/>
              </svg>
              Historia logowań
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Edycja profilu -->
<div class="modal-overlay" id="edit-profile-modal" aria-hidden="true" aria-modal="true" role="dialog">
  <div class="modal-container modal-container--large">
    <button class="modal-close" type="button" aria-label="Zamknij">×</button>
    <div class="modal-header">
      <h3>
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
          <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" fill="currentColor"/>
        </svg>
        Edycja profilu
      </h3>
      <p class="modal-description">Zaktualizuj swoje dane osobowe i kontaktowe.</p>
    </div>

    <form id="profile-edit-form" class="profile-form">
      <div class="form-grid">
        <div class="form-group">
          <label for="first_name">Imię</label>
          <input class="form-input" id="first_name" name="first_name" type="text" value="<?php echo esc_attr($first); ?>" />
        </div>
        <div class="form-group">
          <label for="last_name">Nazwisko</label>
          <input class="form-input" id="last_name" name="last_name" type="text" value="<?php echo esc_attr($last); ?>" />
        </div>
        <div class="form-group">
          <label for="display_name">Wyświetlana nazwa</label>
          <input class="form-input" id="display_name" name="display_name" type="text" value="<?php echo esc_attr($current_user->display_name); ?>" />
          <small class="form-hint">Nazwa widoczna dla innych użytkowników</small>
        </div>
        <div class="form-group">
          <label for="user_email">Adres e-mail</label>
          <input class="form-input" id="user_email" name="user_email" type="email" value="<?php echo esc_attr($current_user->user_email); ?>" />
          <small class="form-hint">Na ten adres przychodzą powiadomienia</small>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-ghost modal-close" type="button">x</button>
        <button id="save-profile-btn" class="btn btn-primary" type="submit">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z" fill="currentColor"/>
          </svg>
          Zapisz zmiany
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: Zmiana hasła -->
<div class="modal-overlay" id="change-password-modal" aria-hidden="true" aria-modal="true" role="dialog">
  <div class="modal-container">
    <button class="modal-close" type="button" aria-label="Zamknij">×</button>
    <div class="modal-header">
      <h3>
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
          <path d="M18,8A2,2 0 0,1 20,10V20A2,2 0 0,1 18,22H6A2,2 0 0,1 4,20V10A2,2 0 0,1 6,8H7V6A5,5 0 0,1 12,1A5,5 0 0,1 17,6V8H18M12,3A3,3 0 0,0 9,6V8H15V6A3,3 0 0,0 12,3Z" fill="currentColor"/>
        </svg>
        Zmiana hasła
      </h3>
      <p class="modal-description">Wprowadź obecne hasło oraz nowe hasło, które chcesz ustawić.</p>
    </div>

    <form id="password-change-form" class="profile-form">
      <div class="form-group">
        <label for="current_password">Obecne hasło</label>
        <div class="password-input-wrapper">
          <input class="form-input" id="current_password" name="current_password" type="password" autocomplete="current-password" />
          <button type="button" class="password-toggle" data-target="current_password">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9M12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17M12,4.5C7,4.5 2.73,7.61 1,12C2.73,16.39 7,19.5 12,19.5C17,19.5 21.27,16.39 23,12C21.27,7.61 17,4.5 12,4.5Z" fill="currentColor"/>
            </svg>
          </button>
        </div>
      </div>
      
      <div class="form-group">
        <label for="new-password">Nowe hasło</label>
        <div class="password-input-wrapper">
          <input class="form-input" id="new-password" name="new_password" type="password" autocomplete="new-password" />
          <button type="button" class="password-toggle" data-target="new-password">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9M12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17M12,4.5C7,4.5 2.73,7.61 1,12C2.73,16.39 7,19.5 12,19.5C17,19.5 21.27,16.39 23,12C21.27,7.61 17,4.5 12,4.5Z" fill="currentColor"/>
            </svg>
          </button>
        </div>
        <div id="password-strength" class="password-strength" style="display:none;">
          <div class="strength-bar">
            <div class="strength-fill"></div>
          </div>
          <div class="strength-text">—</div>
        </div>
      </div>
      
      <div class="form-group">
        <label for="confirm_password">Potwierdź nowe hasło</label>
        <div class="password-input-wrapper">
          <input class="form-input" id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" />
          <button type="button" class="password-toggle" data-target="confirm_password">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9M12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17M12,4.5C7,4.5 2.73,7.61 1,12C2.73,16.39 7,19.5 12,19.5C17,19.5 21.27,16.39 23,12C21.27,7.61 17,4.5 12,4.5Z" fill="currentColor"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-ghost modal-close" type="button">x</button>
        <button id="save-password-btn" class="btn btn-primary" type="submit">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z" fill="currentColor"/>
          </svg>
          Zmień hasło
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: Historia logowań -->
<div class="modal-overlay" id="login-history-modal" aria-hidden="true" aria-modal="true" role="dialog">
  <div class="modal-container modal-container--large">
    <button class="modal-close" type="button" aria-label="Zamknij">×</button>
    <div class="modal-header">
      <h3>
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
          <path d="M13.5,8H12V13L16.28,15.54L17,14.33L13.5,12.25V8M13,3A9,9 0 0,0 4,12H1L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3" fill="currentColor"/>
        </svg>
        Historia logowań
      </h3>
      <p class="modal-description">Lista ostatnich prób logowania na Twoje konto.</p>
    </div>

    <div class="modal-content">
      <div id="login-history-content">
        <div class="loading-spinner-container">
          <div class="loading-spinner"></div>
          <p>Ładowanie historii...</p>
        </div>
      </div>
      
      <div id="login-history-pagination" class="login-history-pagination">
        <!-- Paginacja będzie wstawiana przez JavaScript -->
      </div>
    </div>

    <div class="form-actions">
      <button class="btn btn-ghost modal-close" type="button">x</button>
    </div>
  </div>
</div>

<style>
/* ==========================================================================
   PROFIL — Nowoczesny styl z lepszym UX
   ========================================================================== */

:root {
  --kp-card: #ffffff;
  --kp-bd: #e2e8f0;
  --kp-txt: #1e293b;
  --kp-muted: #64748b;
  --kp-ghost: #f1f5f9;
  --kp-brand: #ED1C24;
  --kp-brand-700: #dc2626;
  --kp-success: #16a34a;
  --kp-warning: #eab308;
  --kp-error: #dc2626;
  --kp-focus: #3b82f6;
  --radius: 12px;
  --radius-lg: 16px;
  --gap: 16px;
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.kp-profile-wrap {
  padding: 20px;
  background: var(--kp-bg);
  min-height: 100vh;
}

.profile-container {
  max-width: 1200px;
  margin: 0 auto;
  color: var(--kp-txt);
}

/* Header profilu */
.profile-header {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center;
  gap: var(--gap);
  margin-bottom: 24px;
  background: var(--kp-card);
  border: 1px solid var(--kp-bd);
  border-radius: var(--radius-lg);
  padding: 24px;
  box-shadow: var(--shadow-sm);
}

.profile-avatar-block {
  display: flex;
  gap: 20px;
  align-items: center;
}

#profile-avatar-container.avatar {
  position: relative;
  width: 80px;
  height: 80px;
  border-radius: 50%;
  overflow: hidden;
  border: 3px solid var(--kp-bd);
  background: #fff;
  cursor: pointer;
  transition: all 0.2s ease;
}

#profile-avatar-container:hover {
  border-color: var(--kp-brand);
  transform: scale(1.02);
}

#profile-avatar-container img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

#profile-avatar-container .avatar-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
  color: #fff;
  background: rgba(0, 0, 0, 0.6);
  opacity: 0;
  transition: opacity 0.2s ease;
}

#profile-avatar-container:hover .avatar-overlay,
#profile-avatar-container:focus-within .avatar-overlay {
  opacity: 1;
}

.profile-name h1 {
  margin: 0 0 8px 0;
  font-size: 28px;
  font-weight: 700;
  line-height: 1.2;
  color: var(--kp-txt);
}

.profile-role {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  background: rgba(237, 28, 36, 0.1);
  color: var(--kp-brand);
  border-radius: 16px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.profile-role::before {
  content: "";
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--kp-brand);
}

/* Przyciski */
.profile-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  border: 1px solid var(--kp-bd);
  border-radius: var(--radius);
  background: var(--kp-card);
  color: var(--kp-txt);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  user-select: none;
}

.btn:hover {
  background: var(--kp-ghost);
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}

.btn:active {
  transform: translateY(0);
}

.btn:focus-visible {
  outline: 2px solid var(--kp-focus);
  outline-offset: 2px;
}

.btn-primary {
  background: var(--kp-brand);
  border-color: var(--kp-brand);
  color: white;
}

.btn-primary:hover {
  background: var(--kp-brand-700);
  border-color: var(--kp-brand-700);
}

.btn-secondary {
  background: var(--kp-ghost);
  border-color: var(--kp-bd);
  color: var(--kp-txt);
}

.btn-outline {
  background: transparent;
  border-color: var(--kp-brand);
  color: var(--kp-brand);
}

.btn-outline:hover {
  background: var(--kp-brand);
  color: white;
}

.btn-ghost {
  background: transparent;
  border-color: var(--kp-bd);
  color: var(--kp-muted);
}

.btn-ghost:hover {
  background: var(--kp-ghost);
  color: var(--kp-txt);
}

/* Grid kart */
.profile-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: var(--gap);
}

/* Karty */
.profile-card {
  background: var(--kp-card);
  border: 1px solid var(--kp-bd);
  border-radius: var(--radius-lg);
  padding: 24px;
  box-shadow: var(--shadow-sm);
  transition: all 0.2s ease;
}

.profile-card:hover {
  box-shadow: var(--shadow-md);
}

.card-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 700;
  font-size: 16px;
  margin-bottom: 20px;
  color: var(--kp-txt);
}

.card-title svg {
  color: var(--kp-brand);
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.card-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
  border-bottom: 1px dashed var(--kp-bd);
}

.card-row:last-child {
  border-bottom: none;
}

.card-row span {
  color: var(--kp-muted);
  font-size: 14px;
}

.card-row b {
  font-weight: 600;
  color: var(--kp-txt);
}

.security-text {
  color: var(--kp-muted);
  font-size: 14px;
  line-height: 1.5;
  margin: 8px 0 16px 0;
}

.security-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

/* Role chips */
.roles {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.role-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--kp-ghost);
  border: 1px solid var(--kp-bd);
  color: var(--kp-txt);
  border-radius: 16px;
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.role-chip::before {
  content: "";
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--kp-brand);
}

/* Modale */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
  transition: all 0.2s ease;
}

.modal-overlay[aria-hidden="true"] {
  opacity: 0;
  pointer-events: none;
}

.modal-container {
  width: min(500px, 100%);
  max-height: 90vh;
  overflow-y: auto;
  background: var(--kp-card);
  border: 1px solid var(--kp-bd);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  position: relative;
  animation: modalSlideIn 0.2s ease-out;
}

.modal-container--large {
  width: min(700px, 100%);
}

@keyframes modalSlideIn {
  from {
    transform: translateY(20px) scale(0.95);
    opacity: 0;
  }
  to {
    transform: translateY(0) scale(1);
    opacity: 1;
  }
}

.modal-header {
  padding: 24px 24px 20px;
  border-bottom: 1px solid var(--kp-bd);
}

.modal-header h3 {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0 0 8px 0;
  font-size: 20px;
  font-weight: 700;
  color: var(--kp-txt);
}

.modal-header h3 svg {
  color: var(--kp-brand);
}

.modal-description {
  margin: 0;
  color: var(--kp-muted);
  font-size: 14px;
  line-height: 1.5;
}

.modal-close {
  position: absolute;
  top: 16px;
  right: 16px;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  border: none;
  background: var(--kp-ghost);
  color: var(--kp-muted);
  cursor: pointer;
  font-size: 18px;
  transition: all 0.2s ease;
}

.modal-close:hover {
  background: var(--kp-bd);
  color: var(--kp-txt);
}

.modal-content {
  padding: 24px;
}

/* Formy */
.profile-form {
  padding: 24px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
  margin-bottom: 24px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 8px;
  color: var(--kp-txt);
}

.form-input {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--kp-bd);
  border-radius: var(--radius);
  background: var(--kp-card);
  color: var(--kp-txt);
  font-size: 14px;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.form-input::placeholder {
  color: var(--kp-muted);
}

.form-input:focus {
  outline: none;
  border-color: var(--kp-brand);
  box-shadow: 0 0 0 3px rgba(237, 28, 36, 0.1);
}

.form-hint {
  font-size: 12px;
  color: var(--kp-muted);
  margin-top: 4px;
  line-height: 1.4;
}

.form-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  padding-top: 20px;
  border-top: 1px solid var(--kp-bd);
}

/* Password input wrapper */
.password-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.password-input-wrapper .form-input {
  padding-right: 45px;
}

.password-toggle {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  color: var(--kp-muted);
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.password-toggle:hover {
  color: var(--kp-txt);
  background: var(--kp-ghost);
}

/* Password strength */
.password-strength {
  margin-top: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.strength-bar {
  height: 6px;
  background: var(--kp-bd);
  border-radius: 3px;
  overflow: hidden;
}

.strength-fill {
  height: 100%;
  width: 0%;
  background: var(--kp-error);
  transition: all 0.3s ease;
  border-radius: 3px;
}

.strength-text {
  font-size: 12px;
  color: var(--kp-muted);
  font-weight: 500;
}

/* Historia logowań */
.login-history-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-height: 400px;
  overflow-y: auto;
  margin-bottom: 20px;
}

.login-history-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: var(--kp-ghost);
  border-radius: var(--radius);
  border: 1px solid var(--kp-bd);
  transition: all 0.2s ease;
}

.login-history-item:hover {
  background: #f8fafc;
  box-shadow: var(--shadow-sm);
}

.login-details {
  flex: 1;
}

.login-date {
  font-weight: 600;
  color: var(--kp-txt);
  font-size: 14px;
  margin-bottom: 4px;
}

.login-ip, .login-agent {
  font-size: 12px;
  color: var(--kp-muted);
  margin-top: 2px;
}

.login-agent {
  max-width: 300px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.login-status {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  padding: 6px 12px;
  border-radius: 16px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.login-status--success {
  background: rgba(22, 163, 74, 0.1);
  color: var(--kp-success);
}

.login-status--failed {
  background: rgba(220, 38, 38, 0.1);
  color: var(--kp-error);
}

.no-data, .error-message {
  text-align: center;
  padding: 40px 20px;
  color: var(--kp-muted);
  font-style: italic;
}

.error-message {
  color: var(--kp-error);
}

.loading-spinner-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  text-align: center;
}

.loading-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--kp-bd);
  border-top-color: var(--kp-brand);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

/* Paginacja */
.login-history-pagination {
  display: flex;
  gap: 8px;
  align-items: center;
  justify-content: center;
  margin: 20px 0;
  flex-wrap: wrap;
}

.login-history-pagination .page-btn,
.login-history-pagination .page-num {
  border: 1px solid var(--kp-bd);
  background: var(--kp-card);
  padding: 8px 12px;
  border-radius: var(--radius);
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s ease;
  color: var(--kp-txt);
}

.login-history-pagination .page-num[aria-current="page"] {
  background: var(--kp-brand);
  border-color: var(--kp-brand);
  color: white;
  cursor: default;
}

.login-history-pagination .page-btn[disabled] {
  opacity: 0.5;
  cursor: not-allowed;
}

.login-history-pagination .page-btn:hover:not([disabled]),
.login-history-pagination .page-num:hover:not([aria-current="page"]) {
  background: var(--kp-ghost);
  border-color: var(--kp-brand);
}

.page-dots {
  padding: 8px 4px;
  color: var(--kp-muted);
}

/* Responsive design */
@media (max-width: 1024px) {
  .profile-grid {
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  }
}

@media (max-width: 768px) {
  .kp-profile-wrap {
    padding: 12px;
  }

  .profile-header {
    grid-template-columns: 1fr;
    padding: 20px 16px;
    gap: 16px;
  }

  .profile-avatar-block {
    flex-direction: column;
    text-align: center;
    gap: 16px;
  }

  .profile-actions {
    justify-content: center;
    width: 100%;
  }

  .profile-actions .btn {
    flex: 1;
    min-width: 0;
    justify-content: center;
  }

  .profile-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .profile-card {
    padding: 20px 16px;
  }

  .form-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }

  #profile-avatar-container.avatar {
    width: 100px;
    height: 100px;
  }

  .profile-name h1 {
    font-size: 24px;
    text-align: center;
  }

  .profile-role {
    justify-content: center;
  }

  .security-actions {
    flex-direction: column;
  }

  .security-actions .btn {
    width: 100%;
    justify-content: center;
  }

  .login-history-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .login-details {
    width: 100%;
  }

  .login-status {
    align-self: flex-start;
  }

  .login-agent {
    max-width: 100%;
  }

  .login-history-pagination {
    gap: 6px;
  }

  .login-history-pagination .page-btn,
  .login-history-pagination .page-num {
    padding: 6px 10px;
    font-size: 12px;
  }
}

@media (max-width: 640px) {
  .card-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }

  .card-row span,
  .card-row b {
    width: 100%;
  }
}

@media (max-width: 480px) {
  .kp-profile-wrap {
    padding: 8px;
  }

  .modal-overlay {
    padding: 10px;
  }

  .modal-container {
    margin: 0;
    width: 100%;
    max-height: calc(100vh - 20px);
  }

  .modal-header {
    padding: 16px;
  }

  .modal-header h3 {
    font-size: 18px;
  }

  .profile-form, .modal-content {
    padding: 16px;
  }

  .form-actions {
    flex-direction: column-reverse;
  }

  .form-actions .btn {
    width: 100%;
    justify-content: center;
  }

  .btn {
    padding: 10px 14px;
    font-size: 13px;
  }

  .profile-actions {
    flex-direction: column;
  }

  .profile-actions .btn {
    width: 100%;
  }

  .profile-header {
    padding: 16px 12px;
  }

  .profile-card {
    padding: 16px 12px;
  }

  .card-title {
    font-size: 14px;
  }

  .card-title svg {
    width: 18px;
    height: 18px;
  }

  #profile-avatar-container.avatar {
    width: 80px;
    height: 80px;
  }

  .profile-name h1 {
    font-size: 20px;
  }

  .login-history-list {
    max-height: 300px;
  }

  .login-history-item {
    padding: 12px;
  }

  .login-history-pagination .page-btn,
  .login-history-pagination .page-num {
    padding: 6px 8px;
    font-size: 11px;
  }

  .modal-close {
    top: 12px;
    right: 12px;
    width: 28px;
    height: 28px;
    font-size: 16px;
  }
}

@media (max-width: 360px) {
  .profile-name h1 {
    font-size: 18px;
  }

  .btn {
    padding: 8px 12px;
    font-size: 12px;
    gap: 6px;
  }

  .btn svg {
    width: 14px;
    height: 14px;
  }

  .login-history-pagination {
    gap: 4px;
  }

  .login-history-pagination .page-btn,
  .login-history-pagination .page-num {
    padding: 4px 6px;
    font-size: 10px;
  }
}

/* Loading states */
.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none !important;
}

.btn.loading {
  position: relative;
  color: transparent;
}

.btn.loading::after {
  content: "";
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  margin: auto;
  border: 2px solid currentColor;
  border-radius: 50%;
  border-top-color: transparent;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Focus management */
.modal-overlay[aria-hidden="false"] {
  opacity: 1;
  pointer-events: all;
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
