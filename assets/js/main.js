/**
 * User Portal - Main JavaScript
 */

// ===== LOGIN FORM VALIDATION =====
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const loginForm = document.getElementById('up-login-form');

        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                const username = document.getElementById('up-username');
                const password = document.getElementById('up-password');

                if (!username.value.trim() || !password.value.trim()) {
                    e.preventDefault();
                    alert('Proszę wypełnić wszystkie pola.');
                    return false;
                }
            });
        }
    });
})();

// ===== DASHBOARD PANEL LOGIC =====
(function () {
  const STORAGE_KEY = 'up_last_view';
  const ALLOWED = new Set(['today', 'my-reports', 'calendar', 'stats', 'profile', 'team', 'logout']);

  // Debug UPPANEL na starcie
  console.log('=== UPPANEL Init ===', window.UPPANEL);
    
  function loadScriptOnce(src, id){
    return new Promise((resolve, reject) => {
      if (id && document.getElementById(id)) return resolve();
      if ([...document.scripts].some(s => s.src === src)) return resolve();
      const s = document.createElement('script');
      if (id) s.id = id;
      s.src = src;
      s.async = true;
      s.onload = resolve;
      s.onerror = () => reject(new Error('Nie udało się wczytać: ' + src));
      document.head.appendChild(s);
    });
  }

  // ===== Helpers ogólne =====
  function root() { return document.querySelector('.kp-panel'); }
  function logoutUrl() { return root()?.dataset.logoutUrl || '/'; }

  function normalize(view) {
    if (!view) return 'today';
    if (view === 'start') return 'today';
    return ALLOWED.has(view) ? view : 'today';
  }

  // Parsuj parametry z hasha
  function parseHashParams() {
    const params = {};
    const hash = location.hash.slice(1); // usuń #
    const pairs = hash.split('&');
    for (const pair of pairs) {
      const [key, value] = pair.split('=');
      if (key && value) params[key] = decodeURIComponent(value);
    }
    return params;
  }

  function setHash(view) {
    // Zachowaj istniejące parametry z hasha (np. period)
    const hashParams = parseHashParams();
    hashParams.view = view;

    const newHash = Object.entries(hashParams)
      .map(([k, v]) => `${k}=${encodeURIComponent(v)}`)
      .join('&');

    const url = new URL(location.href);
    url.hash = newHash;
    history.replaceState(null, '', url.toString());
  }

  function getInitialView() {
    const m = location.hash.match(/view=([a-z0-9\-]+)/i);
    if (m && m[1]) return normalize(m[1]);
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) return normalize(saved);
    const def = document.getElementById('kp-view-container')?.dataset.defaultView || 'today';
    return normalize(def);
  }

  function setActive(view) {
    document.querySelectorAll('.kp-sidebar li[data-view]').forEach(li => {
      li.classList.toggle('active', li.dataset.view === view);
    });
  }

  function showLoading(show) {
    const c = document.getElementById('kp-view-container');
    if (!c) return;
    if (show) {
      c.setAttribute('aria-busy', 'true');
      c.innerHTML = '<div class="kp-loading">Ładowanie…</div>';
    } else {
      c.removeAttribute('aria-busy');
    }
  }

  // Ładowanie skryptów on-demand
  async function ensureScript(src) {
    if (!src) return;
    if ([...document.scripts].some(s => s.src === src)) return;
    await new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = src;
      s.async = true;
      s.onload = resolve;
      s.onerror = reject;
      document.head.appendChild(s);
    });
  }

  // ===== Inity specyficzne dla widoków =====
  function updateMenuTodayStatus(status) {
    const li = document.querySelector('.kp-sidebar li[data-view="today"]');
    if (!li) return;
  }

  // AJAX: zapis szkicu / złożenie raportu - BEZ przeładowania widoku
  async function saveTodayReport(mode, container) {
    const form = container.querySelector('#kptr-form');
    if (!form) return;

    const fd = new FormData(form);
    fd.append('action', 'up_save_report');
    fd.append('mode', mode);
    fd.append('nonce', UPPANEL.report_nonce);

    const footer = container.querySelector('.kptr-modal__footer');
    const prevHTML = footer ? footer.innerHTML : null;
    if (footer) footer.innerHTML = '<span class="kp-saving">Zapisywanie…</span>';

    try {
      const res = await fetch(UPPANEL.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      });
      const json = await res.json();

      if (json && json.success) {
        const status = json.data?.status || '';
        
        // Zamknij modal
        const modal = container.querySelector('#kptr-modal[aria-hidden="false"]');
        if (modal) modal.setAttribute('aria-hidden', 'true');
        
        // Przeładuj widok aby pokazać zaktualizowane dane
        await loadView('today');
      } else {
        alert('Nie udało się zapisać.');
        if (footer && prevHTML !== null) footer.innerHTML = prevHTML;
      }
    } catch (e) {
      alert('Błąd połączenia podczas zapisu.');
      if (footer && prevHTML !== null) footer.innerHTML = prevHTML;
    }
  }

  // Usuwanie raportu
  async function deleteTodayReport() {
    try {
      const fd = new FormData();
      fd.append('action', 'up_save_report');
      fd.append('mode', 'delete');
      fd.append('nonce', UPPANEL.report_nonce);

      const res = await fetch(UPPANEL.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      });
      const json = await res.json();

      if (json && json.success) {
        updateMenuTodayStatus('');
        await loadView('today');
      } else {
        alert('Nie udało się usunąć raportu.');
      }
    } catch (e) {
      alert('Błąd połączenia podczas usuwania.');
    }
  }

  // Init widoku „Dzisiejszy raport"
  function initTodayView(container) {
    if (!container) return;

    const modal = container.querySelector('#kptr-modal');
    const openBtn = container.querySelector('#kptr-open-modal');
    const closeBtn = container.querySelector('#kptr-close');
    const backdrop = container.querySelector('#kptr-backdrop');

    if (modal && openBtn) {
      function onKeydown(e) { if (e.key === 'Escape') close(); }
      function open() {
        modal.setAttribute('aria-hidden', 'false');
        const first = modal.querySelector('input, textarea, select, button');
        if (first) setTimeout(() => first.focus(), 30);
        document.addEventListener('keydown', onKeydown);
      }
      function close() {
        modal.setAttribute('aria-hidden', 'true');
        document.removeEventListener('keydown', onKeydown);
      }
      openBtn.addEventListener('click', open);
      closeBtn && closeBtn.addEventListener('click', close);
      backdrop && backdrop.addEventListener('click', close);
    }

    const draftBtn  = container.querySelector('button[name="kptr_action"][value="draft"]');
    const submitBtns = container.querySelectorAll('button[name="kptr_action"][value="submit"]');
    const deleteBtns = container.querySelectorAll('.kptr-delete-mobile, .kptr-delete-desktop');

    // Obsługa przycisków usuwania (mobile i desktop)
    deleteBtns.forEach(deleteBtn => {
      if (deleteBtn) {
        deleteBtn.addEventListener('click', (e) => {
          e.preventDefault();
          deleteTodayReport();
        });
      }
    });

    // Obsługa przycisków zapisywania (mobile i desktop)
    draftBtn && draftBtn.addEventListener('click', (e) => { e.preventDefault(); saveTodayReport('draft', container);  });
    submitBtns.forEach(submitBtn => {
      submitBtn && submitBtn.addEventListener('click', (e) => { e.preventDefault(); saveTodayReport('submit', container); });
    });
  }

  // Init widoku „Kalendarz"
  async function initCalendarView(container) {
    if (!window.upInit?.calendar && UPPANEL?.calendar_js_url) {
      try { await ensureScript(UPPANEL.calendar_js_url); }
      catch (e) { console.error('Nie udało się wczytać view.calendar.js', e); }
    }
    if (window.upInit?.calendar) {
      window.upInit.calendar(container);
    } else {
      const warn = document.createElement('div');
      warn.className = 'kp-error';
      warn.textContent = 'Nie udało się zainicjalizować kalendarza (brak skryptu).';
      container.appendChild(warn);
    }
  }

  // Mapa initów
  const VIEW_INITS = {
    'today':    initTodayView,
    'calendar': initCalendarView,
    'my-reports': window.upInit?.myReports,
  };

  // ===== Główny loader widoków =====
  async function loadView(view) {
    view = normalize(view);

    if (view === 'logout') {
      location.href = logoutUrl();
      return;
    }

    // Sprawdź czy użytkownik próbuje załadować widok "team" bez odpowiednich uprawnień
    if (view === 'team') {
      const teamMenuItem = document.querySelector('.kp-sidebar li[data-view="team"]');
      if (!teamMenuItem) {
        // Menu item nie istnieje = użytkownik nie ma uprawnień
        const c = document.getElementById('kp-view-container');
        if (c) {
          c.innerHTML = '<div class="kp-error" style="text-align:center;padding:40px;"><h3>Brak dostępu</h3><p>Nie masz uprawnień do przeglądania raportów zespołu.</p><p>Ta funkcja jest dostępna tylko dla administratorów.</p></div>';
        }
        return;
      }
    }

    localStorage.setItem(STORAGE_KEY, view);
    setHash(view);
    setActive(view);
    showLoading(true);

    try {
      const u = new URL(UPPANEL.ajax_url);
      u.searchParams.set('action', 'up_load_view');
      u.searchParams.set('view', view);
      u.searchParams.set('nonce', UPPANEL.nonce);

      // Dodaj dodatkowe parametry z hasha (np. period dla stats, submenu dla team)
      const hashParams = parseHashParams();
      for (const [key, value] of Object.entries(hashParams)) {
        if (key !== 'view' && key !== '_') {
          u.searchParams.set(key, value);
        }
      }

      if (view === 'team') {
        u.searchParams.set('_t', Date.now().toString());
      }
  
      const res = await fetch(u.toString(), { credentials: 'same-origin' });
      const json = await res.json();
  
      const c = document.getElementById('kp-view-container');
      if (!c) return;
  
      if (json && json.success) {
        c.innerHTML = json.data?.html || '';
  
        // per-view init
        if (view === 'my-reports') {
          console.log('Inicjalizacja widoku my-reports');
          try {
            if (window.upInit?.myReports) {
              console.log('my-reports.js już załadowany, wywołuję funkcję');
              window.upInit.myReports(c);
            } else if (UPPANEL?.my_reports_js_url) {
              console.log('Ładowanie my-reports.js z:', UPPANEL.my_reports_js_url);
              await loadScriptOnce(UPPANEL.my_reports_js_url, 'up-my-reports-js');
              console.log('my-reports.js załadowany, wywołuję funkcję');
              window.upInit?.myReports?.(c);
            } else {
              console.error('Brak UPPANEL.my_reports_js_url');
            }
          } catch (e) { 
            console.error('Błąd podczas inicjalizacji my-reports:', e); 
          }
        } else if (view === 'today') {
          if (typeof initTodayView === 'function') {
            initTodayView(c);
          }
        } else if (view === 'calendar') {
          // Załaduj calendar.js jeśli jeszcze nie jest załadowany
          if (!window.upInit?.calendar && UPPANEL?.calendar_js_url) {
            await loadScriptOnce(UPPANEL.calendar_js_url, 'up-calendar-js');
          }
          // Wywołaj inicjalizację
          window.upInit?.calendar?.(c);
        } else if (view === 'stats') {
          if (!window.upInit?.stats && UPPANEL?.stats_js_url) {
            await loadScriptOnce(UPPANEL.stats_js_url, 'up-stats-js');
          }
          window.upInit?.stats?.(c);
        } else if (view === 'profile') {
          console.log('Inicjalizacja widoku profile');
          try {
            if (window.upInit?.profile) {
              console.log('profile.js już załadowany, wywołuję funkcję');
              window.upInit.profile(c);
            } else if (UPPANEL?.profile_js_url) {
              console.log('Ładowanie profile.js z:', UPPANEL.profile_js_url);
              await loadScriptOnce(UPPANEL.profile_js_url, 'up-profile-js');
              console.log('profile.js załadowany, wywołuję funkcję');
              window.upInit?.profile?.(c);
            } else {
              console.error('Brak UPPANEL.profile_js_url');
            }
          } catch (e) {
            console.error('Błąd podczas inicjalizacji profile:', e);
          }
        } else if (view === 'team') {
          console.log('Ładowanie modułu raportów zespołu...');
          if (!window.TeamReports && UPPANEL?.team_js_url) {
            await loadScriptOnce(UPPANEL.team_js_url, 'up-team-reports-js');
          }
          setTimeout(() => {
            if (window.TeamReports) {
              console.log('Reinicjalizacja TeamReports...');
              window.TeamReports.reinitialize();
            } else {
              console.error('window.TeamReports nie jest dostępne');
            }
          }, 200);
        }
  
        c.dispatchEvent(new CustomEvent('up:view:loaded', { detail: { view } }));
  
        const initFn = VIEW_INITS[view];
        if (typeof initFn === 'function') await initFn(c);
      } else {
        c.innerHTML = '<div class="kp-error">Nie udało się załadować widoku.</div>';
      }
    } catch (e) {
      const c = document.getElementById('kp-view-container');
      if (c) c.innerHTML = '<div class="kp-error">Błąd połączenia.</div>';
    } finally {
      showLoading(false);
    }
  }

  // Submenu handlers
  function toggleSubmenu(parentLi) {
    parentLi.classList.toggle('open');
  }

  function handleSubmenuClick(li) {
    const view = li.getAttribute('data-view');
    const submenu = li.getAttribute('data-submenu');

    // Usuń active ze wszystkich elementów menu i submenu
    document.querySelectorAll('.kp-sidebar li[data-view]').forEach(item => {
      item.classList.remove('active');
    });

    // Dodaj active do klikniętego elementu
    li.classList.add('active');

    // Dodaj active do parent (has-submenu)
    const parentLi = li.closest('.has-submenu');
    if (parentLi) {
      parentLi.classList.add('active');
      parentLi.classList.add('open');
    }

    // Załaduj widok z parametrem submenu
    const hashParams = parseHashParams();
    hashParams.view = view;
    if (submenu) {
      hashParams.submenu = submenu;
    }

    const newHash = Object.entries(hashParams)
      .map(([k, v]) => `${k}=${encodeURIComponent(v)}`)
      .join('&');

    location.hash = newHash;
  }

  // Menu click handlers
  document.addEventListener('DOMContentLoaded', function() {
    const menuItems = document.querySelectorAll('.kp-sidebar > ul > li[data-view]:not(.has-submenu)');
    const hasSubmenuItems = document.querySelectorAll('.kp-sidebar li.has-submenu');
    const submenuItems = document.querySelectorAll('.kp-sidebar .submenu li[data-view]');

    // Normalne elementy menu (bez submenu)
    menuItems.forEach(item => {
      item.addEventListener('click', function() {
        const view = this.getAttribute('data-view');
        document.querySelectorAll('.kp-sidebar li[data-view]').forEach(mi => mi.classList.remove('active'));
        this.classList.add('active');
        loadView(view);
      });
    });

    // Elementy z submenu - kliknięcie w parent toggle
    hasSubmenuItems.forEach(item => {
      const menuContent = item.querySelector('.menu-item-content');
      if (menuContent) {
        menuContent.addEventListener('click', function(e) {
          e.stopPropagation();
          toggleSubmenu(item);
        });
      }
    });

    // Elementy wewnątrz submenu
    submenuItems.forEach(item => {
      item.addEventListener('click', function(e) {
        e.stopPropagation();
        handleSubmenuClick(this);
      });
    });
    
    // View specific features
    function initializeViewSpecificFeatures(view) {
      switch(view) {
        case 'team':
          console.log('Inicjalizacja widoku zespołu zakończona');
          break;
        case 'calendar':
          if (window.initializeCalendar) {
            window.initializeCalendar();
          }
          break;
        case 'stats':
          if (window.initializeStats) {
            window.initializeStats();
          }
          break;
      }
    }
    
    // Observer dla raportów zespołu
    const viewContainer = document.getElementById('kp-view-container');
    if (viewContainer) {
      const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
          if (mutation.type === 'childList') {
            const addedNodes = Array.from(mutation.addedNodes);
            const hasTeamReports = addedNodes.some(node => {
              if (node.nodeType === 1) {
                return node.querySelector('#kp-team-data') || 
                       node.id === 'kp-team-data' ||
                       node.querySelector('.team-reports') ||
                       node.classList?.contains('team-reports');
              }
              return false;
            });
            
            if (hasTeamReports && window.TeamReports) {
              console.log('Observer: Wykryto moduł raportów - auto inicjalizacja');
              setTimeout(() => {
                if (window.TeamReports && !window.TeamReports.isInitialized) {
                  window.TeamReports.reinitialize();
                }
              }, 100);
            }
          }
        });
      });
      
      observer.observe(viewContainer, {
        childList: true,
        subtree: true
      });
    }
  });

  // ===== Nawigacja boczna i hash =====
  function bindMenu() {
    document.querySelectorAll('.kp-sidebar li[data-view]').forEach(li => {
      li.addEventListener('click', () => loadView(li.dataset.view));
      li.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); loadView(li.dataset.view); }
      });
    });
  }

  function onHashChange() {
    const m = location.hash.match(/view=([a-z0-9\-]+)/i);
    loadView(normalize(m && m[1] ? m[1] : null));
  }

  // ===== Start =====
  function init() {
    bindMenu();
    loadView(getInitialView());
    window.addEventListener('hashchange', onHashChange);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Expose changeTeamDate globally for use in team.php date selector
  window.changeTeamDate = function(date, submenu) {
    // Get current hash params
    const hashParams = parseHashParams();

    // Update only relevant params
    hashParams.view = 'team';
    hashParams.submenu = submenu;

    // Store date per submenu (pompy_cr_date, pompy_vp_date, etc.)
    const dateKey = `${submenu}_date`;
    hashParams[dateKey] = date;

    // Remove old date param if exists
    delete hashParams.date;

    // Add timestamp to force reload
    hashParams._t = Date.now();

    const newHash = Object.entries(hashParams)
      .map(([k, v]) => `${k}=${encodeURIComponent(v)}`)
      .join('&');

    location.hash = newHash;

    // Trigger loadView to reload with new date
    loadView('team');
  };

  // Expose team report modal functions globally
  window.openTeamReportModal = function(cardElement) {
    const userData = JSON.parse(cardElement.getAttribute('data-user-report'));

    if (!userData || !userData.report) {
      console.error('Brak danych raportu');
      return;
    }

    // Kategorie - muszą być przekazane z PHP
    const categoriesElement = document.getElementById('up-categories-data');
    if (!categoriesElement) {
      console.error('Brak danych kategorii');
      return;
    }
    const categories = JSON.parse(categoriesElement.textContent);

    // Inicjały użytkownika
    const nameParts = userData.name.split(' ');
    let initials = '';
    nameParts.forEach(part => {
      if (part) initials += part.charAt(0);
    });
    initials = initials.substring(0, 2).toUpperCase();

    // Buduj HTML modala
    let modalHTML = `
      <div class="team-modal-overlay" onclick="closeTeamReportModal(event)">
        <div class="team-modal" onclick="event.stopPropagation()">
          <div class="team-modal__header">
            <div class="team-modal__title">
              <div class="team-modal__avatar">${initials}</div>
              <div class="team-modal__user-info">
                <h3>${userData.name}</h3>
                <p>${userData.email}</p>
              </div>
            </div>
            <button class="team-modal__close" onclick="closeTeamReportModal(event)">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="team-modal__body">
    `;

    let hasAnyData = false;

    // Iteruj przez każdą kategorię
    for (const [catKey, catInfo] of Object.entries(categories)) {
      const catTasks = userData.report.tasks && userData.report.tasks[catKey] ? userData.report.tasks[catKey] : {};
      const tasksHTML = [];

      for (const [taskKey, taskLabel] of Object.entries(catInfo.tasks)) {
        const taskData = catTasks[taskKey];
        if (taskData && parseInt(taskData.qty) > 0) {
          hasAnyData = true;
          tasksHTML.push(`
            <div class="task-item">
              <div class="task-item__header">
                <span class="task-item__name">${taskLabel}</span>
                <span class="task-item__qty" style="background: ${catInfo.color};">
                  ${taskData.qty} szt.
                </span>
              </div>
              ${taskData.time ? `
                <div class="task-item__meta">
                  <div class="task-item__time">
                    <i class="far fa-clock"></i>
                    ${taskData.time}
                  </div>
                </div>
              ` : ''}
              ${taskData.note ? `
                <div class="task-item__note" style="border-left-color: ${catInfo.color};">
                  <i class="far fa-comment-dots"></i> ${taskData.note}
                </div>
              ` : ''}
            </div>
          `);
        }
      }

      // Jeśli kategoria ma dane, dodaj sekcję
      if (tasksHTML.length > 0) {
        modalHTML += `
          <div class="team-modal__category-section">
            <div class="team-modal__category-header">
              <div class="team-modal__category-icon"
                   style="background: linear-gradient(135deg, ${catInfo.color} 0%, ${catInfo.color}dd 100%);">
                <i class="fas ${catInfo.icon}"></i>
              </div>
              <h3 class="team-modal__category-name">${catInfo.label}</h3>
            </div>
            <div class="team-modal__tasks-grid">
              ${tasksHTML.join('')}
            </div>
          </div>
        `;
      }
    }

    // Uwagi ogólne
    if (userData.general_notes && userData.general_notes.trim() !== '') {
      hasAnyData = true;
      modalHTML += `
        <div class="team-modal__general-notes">
          <h4><i class="fas fa-clipboard-list"></i> Uwagi ogólne</h4>
          <p>${userData.general_notes}</p>
        </div>
      `;
    }

    // Jeśli brak danych
    if (!hasAnyData) {
      modalHTML += `
        <div class="team-modal__empty">
          <i class="fas fa-inbox"></i>
          <p>Brak danych w raporcie</p>
        </div>
      `;
    }

    modalHTML += `
          </div>
        </div>
      </div>
    `;

    // Wstaw modal do DOM
    const container = document.getElementById('team-report-modal-container');
    if (container) {
      container.innerHTML = modalHTML;
      // Zablokuj scroll
      document.body.style.overflow = 'hidden';
    }
  };

  // Funkcja zamykania modala
  window.closeTeamReportModal = function(event) {
    event.stopPropagation();
    const container = document.getElementById('team-report-modal-container');
    if (container) {
      container.innerHTML = '';
      document.body.style.overflow = '';
    }
  };
})();
