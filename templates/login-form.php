<?php
/**
 * Template: Formularz logowania
 */

if (!defined('ABSPATH')) exit;
?>

<!-- Toast notifications container -->
<div id="kp-login-msg" aria-live="polite">
    <?php if (!empty($error)): ?>
        <div class="kp-alert kp-alert-error"><?php echo esc_html($error); ?></div>
    <?php endif; ?>
</div>

<div class="kp-login-wrap">
    <div class="kp-login-card">

        <div class="kp-login-header">
            <!-- Logo -->
            <div>
                <img src="https://dakro.pl/wp-content/uploads/2025/07/d_reman.png"
                     alt="Logo"
                     class="kp-login-logo">
            </div>

            <h1>System Rejestracji Pracy</h1>
            <p>Zaloguj się, aby wprowadzić dzienny raport</p>
        </div>

        <form id="kp-login-form" class="kp-login-form" method="post" action="" novalidate>
            <?php wp_nonce_field('up_login_action', 'up_login_nonce'); ?>

            <p>
                <label for="kp-user">Nazwa użytkownika</label>
                <input type="text" id="kp-user" name="up_username" placeholder="Wpisz swoją nazwę użytkownika" required autocomplete="username" autofocus>
            </p>

            <p>
                <label for="kp-pass">Hasło</label>
                <div class="kp-pass-wrap">
                    <input type="password" id="kp-pass" name="up_password" placeholder="Wpisz swoje hasło" required autocomplete="current-password">
                    <span id="kp-toggle-pass">
                        <svg id="kp-eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zm0 12a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9z"/>
                            <circle cx="12" cy="12" r="2.5"/>
                        </svg>
                    </span>
                </div>
            </p>

            <p>
                <button type="submit" name="up_login_submit" id="kp-submit" aria-label="Zaloguj się do Systemu Rejestracji Pracy">Zaloguj się</button>
            </p>

            <p class="kp-login-desc">
                Ten system służy do codziennego <strong>rejestrowania pracy</strong> przez pracowników oraz zatwierdzania raportów przez przełożonych.
            </p>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const pass   = document.getElementById('kp-pass');
    const user   = document.getElementById('kp-user');
    const toggle = document.getElementById('kp-toggle-pass');
    const eyeIcon= document.getElementById('kp-eye-icon');
    const form   = document.getElementById('kp-login-form');
    const submit = document.getElementById('kp-submit');

    // Pokaż/ukryj hasło z animacją
    if (toggle) {
        toggle.addEventListener('click', () => {
            const isPassword = pass.type === 'password';
            pass.type = isPassword ? 'text' : 'password';

            // Animacja ikony
            toggle.style.transform = 'translateY(-50%) scale(0.8)';
            setTimeout(() => {
                eyeIcon.innerHTML = isPassword
                    ? '<path d="M12 6C7 6 2.73 9.11 1 13.5c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5C21.27 9.11 17 6 12 6zm0 12a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9z"/>'
                    : '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zm0 12a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9z"/><circle cx="12" cy="12" r="2.5"/>';
                toggle.style.transform = 'translateY(-50%) scale(1)';
            }, 150);
        });
    }

    // Animacja focus na label
    const addFocusAnimation = (input) => {
        const label = input.previousElementSibling;
        if (!label || label.tagName !== 'LABEL') return;

        input.addEventListener('focus', () => {
            label.style.color = '#ED1C24';
            label.style.transform = 'translateY(-2px)';
        });

        input.addEventListener('blur', () => {
            if (!input.value) {
                label.style.color = '#2c3e50';
                label.style.transform = 'translateY(0)';
            }
        });
    };

    if (user) addFocusAnimation(user);
    if (pass) addFocusAnimation(pass);

    // AJAX login handler
    if (form && submit) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const username = user.value.trim();
            const password = pass.value.trim();

            if (!username || !password) {
                // Animacja shake dla pustych pól i toast
                if (!username) shakeElement(user);
                if (!password) shakeElement(pass);

                showToast('Wypełnij wszystkie pola formularza', 'error');
                return false;
            }

            // Animacja ładowania
            submit.style.pointerEvents = 'none';
            submit.style.opacity = '0.7';
            const originalText = submit.textContent;
            submit.innerHTML = '<span style="display:inline-block;animation:spin 1s linear infinite">⟳</span> Logowanie...';

            // Przygotuj dane
            const formData = new FormData(form);
            formData.append('action', 'up_ajax_login');

            try {
                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showToast(data.data.message || 'Zalogowano pomyślnie!', 'ok');

                    // Przekieruj po krótkiej chwili
                    setTimeout(() => {
                        window.location.href = data.data.redirect || '<?php echo home_url('/'); ?>';
                    }, 800);
                } else {
                    // Błąd logowania
                    showToast(data.data.message || 'Nieprawidłowa nazwa użytkownika lub hasło.', 'error');

                    // Przywróć przycisk
                    submit.style.pointerEvents = '';
                    submit.style.opacity = '';
                    submit.textContent = originalText;

                    // Shake dla pól
                    shakeElement(user);
                    shakeElement(pass);
                }
            } catch (error) {
                showToast('Błąd połączenia. Spróbuj ponownie.', 'error');

                // Przywróć przycisk
                submit.style.pointerEvents = '';
                submit.style.opacity = '';
                submit.textContent = originalText;
            }
        });
    }

    // Funkcja shake dla walidacji
    function shakeElement(element) {
        element.style.animation = 'shake 0.5s';
        element.style.borderColor = '#ED1C24';

        setTimeout(() => {
            element.style.animation = '';
        }, 500);
    }

    // Funkcja do pokazywania toast notifications
    function showToast(message, type = 'error') {
        const msgContainer = document.getElementById('kp-login-msg');
        if (!msgContainer) return;

        const toast = document.createElement('div');
        toast.className = `kp-alert kp-alert-${type}`;
        toast.textContent = message;

        msgContainer.appendChild(toast);

        // Auto-hide po 5 sekundach (lub 3 dla sukcesu)
        const hideDelay = type === 'ok' ? 3000 : 5000;
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.4s ease-out';
            setTimeout(() => toast.remove(), 400);
        }, hideDelay);
    }

    // Auto-hide dla istniejących alertów
    document.querySelectorAll('.kp-alert').forEach(alert => {
        setTimeout(() => {
            alert.style.animation = 'slideOutRight 0.4s ease-out';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });

    // Dodaj CSS dla animacji shake, spin i slideOutRight
    const style = document.createElement('style');
    style.textContent = `
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100px);
            }
        }
    `;
    document.head.appendChild(style);

    // Ripple effect na przycisku
    if (submit) {
        submit.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;

            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                border-radius: 50%;
                background: rgba(255,255,255,0.6);
                left: ${x}px;
                top: ${y}px;
                pointer-events: none;
                animation: ripple 0.6s ease-out;
            `;

            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });

        const rippleStyle = document.createElement('style');
        rippleStyle.textContent = `
            @keyframes ripple {
                from {
                    transform: scale(0);
                    opacity: 1;
                }
                to {
                    transform: scale(2);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(rippleStyle);
    }
});
</script>
