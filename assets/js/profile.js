// assets/js/profile.js - Kompletny plik z obsługą profilu użytkownika
(function() {
    'use strict';

    const CONFIG = {
        maxFileSize: 30 * 1024 * 1024, // 2MB
        allowedTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        passwordMinLength: 8,
        loginHistoryPerPage: 5,
        debug: true
    };

    // Funkcja debugowania
    function debug(message, data = null) {
        if (CONFIG.debug) {
            console.log('Profile:', message, data || '');
        }
    }

    // Funkcja pomocnicza do requestów AJAX z lepszym logowaniem błędów
    async function ajaxRequest(action, formData) {
        formData.append('action', action);
        formData.append('nonce', window.UPPANEL.profile_nonce);

        debug('AJAX Request:', {
            action: action,
            ajax_url: window.UPPANEL.ajax_url,
            nonce: window.UPPANEL.profile_nonce,
            formDataKeys: Array.from(formData.keys())
        });

        try {
            const response = await fetch(window.UPPANEL.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            debug('AJAX Response status:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            debug('AJAX Response data:', result);

            return result;
        } catch (error) {
            console.error('Profile: AJAX Error:', error);
            throw error;
        }
    }

    // Główna funkcja inicjalizacyjna
    function initProfile(container) {
        if (!container) {
            console.warn('Profile: Brak kontenera do inicjalizacji');
            return;
        }

        debug('Inicjalizacja profilu');
        
        // Sprawdź czy UPPANEL jest dostępny
        if (!window.UPPANEL || !window.UPPANEL.profile_nonce) {
            console.error('Profile: Brak wymaganych danych UPPANEL:', window.UPPANEL);
            showNotification('Błąd konfiguracji. Odśwież stronę.', 'error');
            return;
        }

        debug('UPPANEL dane:', window.UPPANEL);

        // Inicjalizacja wszystkich komponentów
        initModalHandling(container);
        initSecurityButtons(container);
        initAvatarChange(container);
        initFormHandling(container);
        initPasswordStrength(container);
        initPasswordToggles(container);

        debug('Profil zainicjalizowany pomyślnie');
    }

    // Obsługa modali
    function initModalHandling(container) {
        debug('Inicjalizacja obsługi modali');
        
        const editButton = container.querySelector('.btn-edit-profile');
        const modals = document.querySelectorAll('.modal-overlay');

        if (editButton) {
            editButton.addEventListener('click', function(e) {
                e.preventDefault();
                debug('Kliknięcie przycisku edycji profilu');
                openModal('edit-profile-modal');
            });
        }

        modals.forEach(modal => {
            const closeButtons = modal.querySelectorAll('.modal-close');
            closeButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeModal(modal.id);
                });
            });

            // Zamknij modal po kliknięciu w tło
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal(modal.id);
                }
            });
        });

        // Obsługa klawisza Escape
        function handleEscKey(e) {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal-overlay[aria-hidden="false"]');
                if (openModal) closeModal(openModal.id);
            }
        }
        document.addEventListener('keydown', handleEscKey);

        // Cleanup function
        container._profileCleanup = container._profileCleanup || [];
        container._profileCleanup.push(() => {
            document.removeEventListener('keydown', handleEscKey);
        });
    }

    function openModal(modalId) {
        debug('Otwieranie modala:', modalId);
        const modal = document.getElementById(modalId);
        if (!modal) {
            console.error('Profile: Nie znaleziono modala:', modalId);
            return;
        }

        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        // Focus na pierwszym elemencie
        setTimeout(() => {
            const firstInput = modal.querySelector('input:not([type="hidden"]):not([hidden]), textarea, select, button:not(.modal-close)');
            if (firstInput) {
                firstInput.focus();
                debug('Focus ustawiony na:', firstInput);
            }
        }, 100);
    }

    function closeModal(modalId) {
        debug('Zamykanie modala:', modalId);
        const modal = document.getElementById(modalId);
        if (!modal) return;

        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';

        // Reset formularzy (ale nie edycji profilu - żeby zachować zmiany)
        const forms = modal.querySelectorAll('form');
        forms.forEach(form => {
            if (form.id !== 'profile-edit-form') {
                form.reset();
            }
        });

        // Wyczyść wskaźnik siły hasła
        const strengthIndicator = modal.querySelector('#password-strength');
        if (strengthIndicator) {
            strengthIndicator.style.display = 'none';
        }
    }

    // Obsługa przycisków bezpieczeństwa
    function initSecurityButtons(container) {
        debug('Inicjalizacja przycisków bezpieczeństwa');
        
        const securityButtons = container.querySelectorAll('.security-btn');
        debug('Znaleziono przycisków bezpieczeństwa:', securityButtons.length);
        
        securityButtons.forEach(button => {
            button.addEventListener('click', function() {
                const action = this.dataset.action;
                debug('Akcja bezpieczeństwa:', action);
                
                switch (action) {
                    case 'change-password':
                        openModal('change-password-modal');
                        break;
                    case 'login-history':
                        openModal('login-history-modal');
                        loadLoginHistory(1, CONFIG.loginHistoryPerPage);
                        break;
                    default:
                        console.warn('Profile: Nieznana akcja:', action);
                        showNotification('Nieznana akcja', 'error');
                }
            });
        });
    }

    // Obsługa zmiany avatara
    function initAvatarChange(container) {
        debug('Inicjalizacja zmiany avatara');
        
        const avatarContainer = container.querySelector('#profile-avatar-container');
        const avatarUpload = container.querySelector('#avatar-upload');
        const avatarImg = container.querySelector('#profile-avatar-img');
        
        if (!avatarContainer || !avatarUpload || !avatarImg) {
            console.warn('Profile: Nie znaleziono elementów avatara');
            return;
        }

        avatarContainer.addEventListener('click', () => {
            debug('Kliknięcie w avatar');
            avatarUpload.click();
        });

        avatarContainer.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                avatarUpload.click();
            }
        });

        avatarUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            debug('Wybrano plik avatara:', file.name, 'Rozmiar:', file.size, 'Typ:', file.type);

            // Walidacja typu pliku
            if (!CONFIG.allowedTypes.includes(file.type)) {
                showNotification('Dozwolone są tylko pliki: JPG, PNG, GIF, WebP', 'error');
                return;
            }

            // Walidacja rozmiaru
            if (file.size > CONFIG.maxFileSize) {
                showNotification('Plik jest za duży. Maksymalny rozmiar to 2MB', 'error');
                return;
            }

            // Podgląd obrazka
            const reader = new FileReader();
            reader.onload = (e) => {
                avatarImg.src = e.target.result;
            };
            reader.readAsDataURL(file);

            // Upload
            uploadAvatar(file);
        });
    }

    // Upload avatara
    async function uploadAvatar(file) {
        debug('Rozpoczynanie uploadu avatara');
        showNotification('Przesyłanie avatara...', 'info');
        
        const formData = new FormData();
        formData.append('action', 'up_upload_avatar');
        formData.append('avatar', file);
        formData.append('nonce', window.UPPANEL.profile_nonce);

        debug('FormData dla avatara:', {
            action: 'up_upload_avatar',
            nonce: window.UPPANEL.profile_nonce,
            fileSize: file.size
        });

        try {
            const response = await fetch(window.UPPANEL.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            debug('Odpowiedź uploadu avatara - status:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            debug('Odpowiedź uploadu avatara - dane:', result);

            if (result.success) {
                showNotification(result.data?.message || 'Avatar został zaktualizowany', 'success');
                
                // Aktualizuj avatar w topbarze jeśli istnieje
                const topbarAvatar = document.querySelector('#up-avatar-img');
                if (topbarAvatar && result.data?.avatar_url) {
                    topbarAvatar.src = result.data.avatar_url;
                    debug('Zaktualizowano avatar w topbarze');
                }
            } else {
                throw new Error(result.data?.message || 'Błąd przesyłania');
            }
        } catch (error) {
            console.error('Profile: Błąd uploadu avatara:', error);
            showNotification('Nie udało się zaktualizować avatara: ' + error.message, 'error');
        }
    }

    // Obsługa formularzy
    function initFormHandling(container) {
        debug('Inicjalizacja obsługi formularzy');
        
        const profileForm = container.querySelector('#profile-edit-form');
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                e.preventDefault();
                debug('Submit formularza profilu');
                saveProfile(this);
            });
        }

        const passwordForm = container.querySelector('#password-change-form');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                e.preventDefault();
                debug('Submit formularza hasła');
                changePassword(this);
            });
        }
    }

    // Zapis profilu
    async function saveProfile(form) {
        debug('Rozpoczynanie zapisu profilu');
        
        const formData = new FormData(form);
        formData.append('action', 'up_update_profile');
        formData.append('nonce', window.UPPANEL.profile_nonce);

        // Debug - pokaż dane formularza
        const formObject = {};
        formData.forEach((value, key) => {
            formObject[key] = value;
        });
        debug('Dane formularza profilu:', formObject);

        const btn = document.querySelector('#save-profile-btn');
        const originalText = btn.textContent;
        
        // Loading state
        btn.textContent = 'Zapisywanie...';
        btn.disabled = true;
        btn.classList.add('loading');

        try {
            const response = await fetch(window.UPPANEL.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            debug('Odpowiedź zapisu profilu - status:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            debug('Odpowiedź zapisu profilu - dane:', result);

            if (result.success) {
                showNotification(result.data?.message || 'Profil został zaktualizowany', 'success');
                closeModal('edit-profile-modal');
                
                // Odśwież stronę po 1 sekundzie
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                throw new Error(result.data?.message || 'Błąd zapisu profilu');
            }
        } catch (error) {
            console.error('Profile: Błąd zapisu profilu:', error);
            showNotification('Nie udało się zaktualizować profilu: ' + error.message, 'error');
        } finally {
            btn.textContent = originalText;
            btn.disabled = false;
            btn.classList.remove('loading');
        }
    }

    // Zmiana hasła
    async function changePassword(form) {
        debug('Rozpoczynanie zmiany hasła');
        
        const formData = new FormData(form);
        const currentPassword = formData.get('current_password');
        const newPassword = formData.get('new_password');
        const confirmPassword = formData.get('confirm_password');

        debug('Dane hasła:', {
            current_length: currentPassword ? currentPassword.length : 0,
            new_length: newPassword ? newPassword.length : 0,
            confirm_length: confirmPassword ? confirmPassword.length : 0
        });

        // Walidacja po stronie klienta
        if (!currentPassword || !newPassword || !confirmPassword) {
            showNotification('Wszystkie pola są wymagane', 'error');
            return;
        }

        if (newPassword.length < CONFIG.passwordMinLength) {
            showNotification(`Hasło musi mieć co najmniej ${CONFIG.passwordMinLength} znaków`, 'error');
            return;
        }

        if (newPassword !== confirmPassword) {
            showNotification('Hasła nie są identyczne', 'error');
            return;
        }

        formData.append('action', 'up_change_password');
        formData.append('nonce', window.UPPANEL.profile_nonce);

        const btn = document.querySelector('#save-password-btn');
        const originalText = btn.textContent;
        
        // Loading state
        btn.textContent = 'Zmienianie...';
        btn.disabled = true;
        btn.classList.add('loading');

        try {
            const response = await fetch(window.UPPANEL.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            debug('Odpowiedź zmiany hasła - status:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            debug('Odpowiedź zmiany hasła - dane:', result);

            if (result.success) {
                showNotification(result.data?.message || 'Hasło zostało zmienione', 'success');
                closeModal('change-password-modal');
            } else {
                throw new Error(result.data?.message || 'Błąd zmiany hasła');
            }
        } catch (error) {
            console.error('Profile: Błąd zmiany hasła:', error);
            showNotification('Nie udało się zmienić hasła: ' + error.message, 'error');
        } finally {
            btn.textContent = originalText;
            btn.disabled = false;
            btn.classList.remove('loading');
        }
    }

    // Inicjalizacja wskaźnika siły hasła
    function initPasswordStrength(container) {
        debug('Inicjalizacja wskaźnika siły hasła');
        
        const input = container.querySelector('#new-password');
        const indicator = container.querySelector('#password-strength');
        
        if (!input || !indicator) {
            debug('Nie znaleziono elementów siły hasła');
            return;
        }

        input.addEventListener('input', function() {
            const password = this.value;
            
            if (!password) {
                indicator.style.display = 'none';
                return;
            }

            indicator.style.display = 'block';
            const strength = calculatePasswordStrength(password);
            updateStrengthIndicator(indicator, strength);
            debug('Siła hasła:', strength);
        });
    }

    // Obliczanie siły hasła
    function calculatePasswordStrength(password) {
        let score = 0;
        let level = 'weak';

        // Długość
        if (password.length >= 8) score++;
        if (password.length >= 12) score++;

        // Różne typy znaków
        if (/[a-z]/.test(password)) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;

        // Określenie poziomu
        if (score >= 5) level = 'strong';
        else if (score >= 3) level = 'medium';

        return { score, level };
    }

    // Aktualizacja wskaźnika siły hasła
    function updateStrengthIndicator(indicator, strength) {
        const fill = indicator.querySelector('.strength-fill');
        const text = indicator.querySelector('.strength-text');
        
        const colors = {
            weak: '#dc2626',
            medium: '#eab308',
            strong: '#16a34a'
        };
        
        const labels = {
            weak: 'Słabe hasło',
            medium: 'Średnie hasło',
            strong: 'Silne hasło'
        };

        const percentage = Math.min(100, (strength.score / 6) * 100);
        
        fill.style.width = `${percentage}%`;
        fill.style.background = colors[strength.level];
        text.textContent = labels[strength.level];
        text.style.color = colors[strength.level];
    }

    // Przełączanie widoczności hasła
    function initPasswordToggles(container) {
        debug('Inicjalizacja przełączników hasła');
        
        const toggleButtons = container.querySelectorAll('.password-toggle');
        debug('Znaleziono przełączników hasła:', toggleButtons.length);
        
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.dataset.target;
                const input = document.getElementById(targetId);
                
                if (!input) {
                    debug('Nie znaleziono inputa dla ID:', targetId);
                    return;
                }

                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                
                debug('Przełączono widoczność hasła:', targetId, 'na:', input.type);
                
                // Zmiana ikony
                const svg = this.querySelector('svg path');
                if (svg) {
                    if (isPassword) {
                        // Ikona "ukryj"
                        svg.setAttribute('d', 'M2,5.27L3.28,4L20,20.72L18.73,22L15.65,18.92C14.5,19.3 13.28,19.5 12,19.5C7,19.5 2.73,16.39 1,12C1.69,10.24 2.79,8.69 4.19,7.46L2,5.27M12,9A3,3 0 0,1 15,12C15,12.35 14.94,12.69 14.83,13L11,9.17C11.31,9.06 11.65,9 12,9M12,4.5C17,4.5 21.27,7.61 23,12C22.18,14.08 20.79,15.88 19,17.19L17.58,15.76C18.94,14.82 20.06,13.54 20.82,12C19.17,8.64 15.76,6.5 12,6.5C10.91,6.5 9.84,6.68 8.84,7L7.3,5.47C8.74,4.85 10.33,4.5 12,4.5M3.18,12C4.83,15.36 8.24,17.5 12,17.5C12.69,17.5 13.37,17.43 14,17.29L11.72,15C10.29,14.85 9.15,13.71 9,12.28L5.6,8.87C4.61,9.72 3.78,10.78 3.18,12Z');
                    } else {
                        // Ikona "pokaż"
                        svg.setAttribute('d', 'M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9M12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17M12,4.5C7,4.5 2.73,7.61 1,12C2.73,16.39 7,19.5 12,19.5C17,19.5 21.27,16.39 23,12C21.27,7.61 17,4.5 12,4.5Z');
                    }
                }
            });
        });
    }

    // Historia logowań z paginacją
    async function loadLoginHistory(page = 1, perPage = CONFIG.loginHistoryPerPage) {
        const content = document.getElementById('login-history-content');
        const pagination = document.getElementById('login-history-pagination');
        
        if (!content) {
            console.warn('Profile: Nie znaleziono kontenera historii logowań');
            return;
        }

        debug('Ładowanie historii logowań, strona:', page);

        // Loading state
        content.innerHTML = `
            <div class="loading-spinner-container">
                <div class="loading-spinner"></div>
                <p>Ładowanie historii...</p>
            </div>
        `;
        
        if (pagination) pagination.innerHTML = '';

        try {
            const params = new URLSearchParams({
                action: 'up_get_login_history',
                nonce: window.UPPANEL.profile_nonce,
                page: String(page),
                per_page: String(perPage),
            });

            debug('Parametry historii logowań:', params.toString());

            const response = await fetch(window.UPPANEL.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            });

            debug('Odpowiedź historii logowań - status:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            debug('Odpowiedź historii logowań - dane:', result);

            if (result.success) {
                const items = result.data?.items || [];
                const meta = result.data?.meta || { total: 0, page: 1, per_page: perPage, pages: 1 };
                
                renderLoginHistory(content, items);
                if (pagination) renderLoginPagination(pagination, meta);
            } else {
                throw new Error(result.data?.message || 'Błąd ładowania historii');
            }
        } catch (error) {
            console.error('Profile: Błąd ładowania historii logowań:', error);
            content.innerHTML = '<p class="error-message">Nie udało się załadować historii logowań</p>';
        }
    }

    // Renderowanie historii logowań
    function renderLoginHistory(container, history) {
        if (!history || history.length === 0) {
            container.innerHTML = '<p class="no-data">Brak zapisanych logowań</p>';
            return;
        }

        const html = history.map(login => {
            const dateISO = login.date || '';
            const date = dateISO ? new Date(dateISO).toLocaleString('pl-PL') : '—';
            const statusClass = login.success ? 'success' : 'failed';
            const statusIcon = login.success ?
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z" fill="currentColor"/></svg>' :
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z" fill="currentColor"/></svg>';

            return `
                <div class="login-history-item">
                    <div class="login-details">
                        <div class="login-date">${escapeHtml(date)}</div>
                        <div class="login-ip">${escapeHtml(login.ip || 'Nieznane IP')}</div>
                        ${login.user_agent ? `<div class="login-agent" title="${escapeHtml(login.user_agent)}">${escapeHtml(login.user_agent.substring(0, 50))}${login.user_agent.length > 50 ? '...' : ''}</div>` : ''}
                    </div>
                    <div class="login-status login-status--${statusClass}">
                        ${statusIcon}
                        <span>${login.success ? 'Udane' : 'Nieudane'}</span>
                    </div>
                </div>
            `;
        }).join('');

        container.innerHTML = `<div class="login-history-list">${html}</div>`;
    }

    // Renderowanie paginacji
    function renderLoginPagination(container, meta) {
        if (!container) return;
        
        const { page, pages } = meta || { page: 1, pages: 1 };
        if (pages <= 1) {
            container.innerHTML = '';
            return;
        }

        const prevDisabled = page <= 1 ? 'disabled' : '';
        const nextDisabled = page >= pages ? 'disabled' : '';

        // Generowanie numerów stron
        const nums = [];
        const addNum = (n) => nums.push(n);
        const addRange = (start, end) => {
            for (let i = start; i <= end; i++) addNum(i);
        };

        addNum(1);
        if (page > 3) nums.push('dots-start');
        addRange(Math.max(2, page - 1), Math.min(pages - 1, page + 1));
        if (page < pages - 2) nums.push('dots-end');
        if (pages > 1) addNum(pages);

        const numsHtml = nums.map(n => {
            if (n === 'dots-start' || n === 'dots-end') {
                return `<span class="page-dots">…</span>`;
            }
            const current = n === page ? 'aria-current="page"' : '';
            return `<button class="page-num" data-page="${n}" ${current}>${n}</button>`;
        }).join('');

        container.innerHTML = `
            <button class="page-btn" data-action="prev" ${prevDisabled}>Poprzednia</button>
            ${numsHtml}
            <button class="page-btn" data-action="next" ${nextDisabled}>Następna</button>
        `;

        // Obsługa kliknięć w paginacji
        container.onclick = (e) => {
            const btn = e.target.closest('button');
            if (!btn || btn.disabled) return;

            const action = btn.dataset.action;
            let targetPage = page;

            if (action === 'prev' && page > 1) {
                targetPage = page - 1;
            } else if (action === 'next' && page < pages) {
                targetPage = page + 1;
            } else if (btn.classList.contains('page-num')) {
                targetPage = parseInt(btn.dataset.page, 10) || page;
            }

            if (targetPage !== page) {
                loadLoginHistory(targetPage, CONFIG.loginHistoryPerPage);
            }
        };
    }

    // System powiadomień
    function showNotification(message, type = 'info') {
        debug('Powiadomienie:', type, message);
        
        // Usuń istniejące powiadomienia
        const existing = document.querySelectorAll('.profile-notification');
        existing.forEach(n => n.remove());

        const notification = document.createElement('div');
        notification.className = `profile-notification profile-notification--${type}`;
        
        const icons = {
            info: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M13,9H11V7H13M13,17H11V11H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z" fill="currentColor"/></svg>',
            success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z" fill="currentColor"/></svg>',
            warning: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M13,14H11V10H13M13,18H11V16H13M1,21H23L12,2L1,21Z" fill="currentColor"/></svg>',
            error: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z" fill="currentColor"/></svg>'
        };

        notification.innerHTML = `
            <div class="notification-content">
                <div class="notification-icon">${icons[type] || icons.info}</div>
                <span class="notification-message">${escapeHtml(message)}</span>
                <button class="notification-close" aria-label="Zamknij">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z" fill="currentColor"/>
                    </svg>
                </button>
            </div>
        `;

        // Dodaj style jeśli nie istnieją
        if (!document.getElementById('profile-notifications-style')) {
            addNotificationStyles();
        }

        document.body.appendChild(notification);
        
        // Obsługa zamknięcia
        notification.querySelector('.notification-close').addEventListener('click', () => {
            removeNotification(notification);
        });

        // Auto-hide dla powiadomień innych niż błędy
        if (type !== 'error') {
            setTimeout(() => {
                if (notification.parentNode) {
                    removeNotification(notification);
                }
            }, 5000);
        }
    }

    // Usuwanie powiadomienia z animacją
    function removeNotification(notification) {
        notification.style.animation = 'slideOutRight .3s ease-in';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }

    // Dodawanie stylów powiadomień
    function addNotificationStyles() {
        const style = document.createElement('style');
        style.id = 'profile-notifications-style';
        style.textContent = `
            @keyframes slideInRight { 
                from { transform: translateX(100%); opacity: 0; } 
                to { transform: translateX(0); opacity: 1; } 
            }
            @keyframes slideOutRight { 
                from { transform: translateX(0); opacity: 1; } 
                to { transform: translateX(100%); opacity: 0; } 
            }
            .profile-notification { 
                position: fixed; 
                top: 20px; 
                right: 20px; 
                z-index: 1001; 
                padding: 16px; 
                background: #fff; 
                border-radius: 12px; 
                box-shadow: 0 10px 25px rgba(0,0,0,.15); 
                max-width: 400px; 
                animation: slideInRight .3s ease-out; 
                border-left: 4px solid #3b82f6; 
            }
            .profile-notification--info { border-left-color: #3b82f6; }
            .profile-notification--success { border-left-color: #16a34a; }
            .profile-notification--warning { border-left-color: #eab308; }
            .profile-notification--error { border-left-color: #dc2626; }
            .notification-content { display: flex; align-items: center; gap: 12px; }
            .notification-icon { color: #64748b; flex-shrink: 0; }
            .notification-message { flex: 1; font-size: 14px; color: #1e293b; line-height: 1.4; }
            .notification-close { background: none; border: none; cursor: pointer; padding: 4px; border-radius: 4px; color: #64748b; flex-shrink: 0; }
            .notification-close:hover { background: #f1f5f9; color: #1e293b; }
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
                border: 3px solid #dadce0; 
                border-top-color: #1a73e8; 
                border-radius: 50%; 
                animation: spin 1s linear infinite; 
                margin-bottom: 16px; 
            }
            @keyframes spin { to { transform: rotate(360deg); } }
            .login-history-list { 
                display: flex; 
                flex-direction: column; 
                gap: 12px; 
                max-height: 400px; 
                overflow-y: auto; 
            }
            .login-history-item { 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                padding: 12px 16px; 
                background: #f8f9fa; 
                border-radius: 8px; 
                border: 1px solid #e8eaed; 
            }
            .login-details { flex: 1; }
            .login-date { 
                font-weight: 500; 
                color: #202124; 
                font-size: 14px; 
            }
            .login-ip, .login-agent { 
                font-size: 12px; 
                color: #5f6368; 
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
                font-weight: 500; 
                padding: 4px 8px; 
                border-radius: 12px; 
                text-transform: uppercase; 
                letter-spacing: .5px; 
            }
            .login-status--success { 
                background: rgba(19,115,51,.1); 
                color: #137333; 
            }
            .login-status--failed { 
                background: rgba(217,48,37,.1); 
                color: #d93025; 
            }
            .no-data, .error-message { 
                text-align: center; 
                padding: 40px 20px; 
                color: #5f6368; 
                font-style: italic; 
            }
            .error-message { color: #d93025; }
            .login-history-pagination {
                display: flex;
                gap: 8px;
                align-items: center;
                justify-content: center;
                margin: 14px 0 4px;
                flex-wrap: wrap;
            }
            .login-history-pagination .page-btn,
            .login-history-pagination .page-num {
                border: 1px solid #e2e8f0;
                background: #fff;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 13px;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .login-history-pagination .page-num[aria-current="page"] {
                background: #ED1C24;
                border-color: #ED1C24;
                color: #fff;
                cursor: default;
            }
            .login-history-pagination .page-btn[disabled] {
                opacity: .5;
                cursor: not-allowed;
            }
            .login-history-pagination .page-btn:hover:not([disabled]),
            .login-history-pagination .page-num:hover:not([aria-current="page"]) {
                background: #f1f5f9;
                border-color: #cbd5e1;
            }
        `;
        document.head.appendChild(style);
    }

    // Escape HTML dla bezpieczeństwa
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // Cleanup funkcja
    function cleanupProfile(container) {
        if (container && container._profileCleanup) {
            container._profileCleanup.forEach(fn => {
                if (typeof fn === 'function') fn();
            });
            container._profileCleanup = [];
        }
    }

    // Globalna rejestracja
    window.upInit = window.upInit || {};
    window.upInit.profile = function(container) {
        debug('Wywoływanie upInit.profile');
        cleanupProfile(container);
        initProfile(container);
    };

    // Export dla modułów
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { initProfile, cleanupProfile };
    }

    // Auto-inicjalizacja przy ładowaniu DOM
    document.addEventListener('DOMContentLoaded', function() {
        debug('DOMContentLoaded event');
        const profileContainer = document.querySelector('.kp-profile-wrap .profile-container');
        if (profileContainer && !profileContainer._profileInitialized) {
            debug('Auto-inicjalizacja profilu przy DOMContentLoaded');
            if (window.upInit && window.upInit.profile) {
                window.upInit.profile(profileContainer);
                profileContainer._profileInitialized = true;
            }
        }
    });

})();
